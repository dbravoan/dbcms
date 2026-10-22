<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Services;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Models\User;
use App\Services\Ai\AntiAiEditorialService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class EditorialQualityGate
{
    public function __construct(private readonly ModuleSettingsServiceInterface $settingsService, private readonly AntiAiEditorialService $antiAi, private readonly EditorialPolicy $policy) {}

    public function isEditorialEnforcementEnabled(): bool
    {
        try {
            return (bool) $this->settingsService->get('content_audit', 'enforce_editorial_quality', false);
        } catch (\Throwable) {
            return false;
        }
    }

    public function minEditorialScore(): int
    {
        try {
            return max(0, min(100, (int) $this->settingsService->get('content_audit', 'min_anti_ai_score', 85)));
        } catch (\Throwable) {
            return 85;
        }
    }

    public function enforceRequest(Model $entity, User $user, bool $requiresAudit): void
    {
        if (! $this->isEditorialEnforcementEnabled() || ! $requiresAudit) {
            return;
        }

        [$title, $content] = $this->requestEditorialPayload();

        if ($title === '' || trim($content) === '') {
            // Nothing assessable in this save path (no translation payload).
            return;
        }

        $result = $this->antiAi->validateEditorialTone($title, $content);
        $min = $this->minEditorialScore();

        $clicheFree = $result['cliches_found'] === [];
        if ($result['anti_ai_score'] >= $min && $clicheFree) {
            return;
        }

        $reasons = [];
        if (! $clicheFree) {
            $reasons[] = 'Expresiones cliché detectadas: '.implode(', ', array_slice($result['cliches_found'], 0, 3)).'.';
        }
        foreach ($result['recommendations'] as $recommendation) {
            $reasons[] = $recommendation;
        }

        throw ValidationException::withMessages([
            'editorial_quality' => 'El contenido no supera el control de calidad editorial (puntuación '
                .$result['anti_ai_score'].' de '.$min.'). '.implode(' ', array_slice($reasons, 0, 2)),
        ]);
    }

    /** @param array<string,mixed> $snapshot */
    public function enforceSnapshot(Model $entity, User $user, array $snapshot): void
    {
        if (! $this->policy->isEnabled() || ! $this->isEditorialEnforcementEnabled() || ! $this->policy->requiresAudit($entity, $user)) {
            return;
        }
        $payloads = [];
        foreach ((array) ($snapshot['translation_records'] ?? []) as $translation) {
            if (! is_array($translation)) {
                continue;
            }
            $locale = (string) ($translation['locale'] ?? 'es');
            $payloads[] = [$locale, (string) ($translation['title'] ?? $translation['name'] ?? ''), $this->editorialText($translation)];
        }
        foreach ((array) ($snapshot['section_records'] ?? []) as $section) {
            foreach ((array) ($section['translations'] ?? []) as $translation) {
                $content = $translation['content_json'] ?? [];
                if (is_string($content)) {
                    $content = json_decode($content, true);
                }
                if (is_array($content)) {
                    $payloads[] = [(string) ($translation['locale'] ?? 'es'), (string) ($content['title'] ?? ''), $this->editorialText($content)];
                }
            }
        }
        foreach ($payloads as [$locale, $title, $content]) {
            if (trim($content) === '') {
                continue;
            }
            $result = $this->antiAi->validateEditorialTone($title, $content, $locale);
            if ($result['anti_ai_score'] < $this->minEditorialScore() || $result['cliches_found'] !== []) {
                throw ValidationException::withMessages(['editorial_quality' => "El texto en {$locale} no supera la revisión de estilo (heurística {$result['anti_ai_score']}/{$this->minEditorialScore()}). ".implode(' ', array_slice($result['recommendations'], 0, 2))]);
            }
        }
    }

    /** @param array<string,mixed> $values */
    private function editorialText(array $values): string
    {
        $text = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $text[] = $this->editorialText($value);
            } elseif (is_string($value) && in_array($key, ['title', 'name', 'content', 'text', 'summary', 'description', 'subtitle', 'heading', 'question', 'answer', 'caption', 'label', 'submit_button_text', 'success_message'], true)) {
                $text[] = strip_tags($value);
            }
        }

        return implode("\n", $text);
    }

    /**
     * Resolves the editorial payload (title + content) of the current request.
     *
     * @return array{0: string, 1: string}
     */
    protected function requestEditorialPayload(): array
    {
        $translations = (array) (request()?->input('translations') ?? []);

        $first = is_array(reset($translations)) ? (array) reset($translations) : [];

        return [
            (string) ($first['title'] ?? ''),
            (string) ($first['content'] ?? ''),
        ];
    }
}
