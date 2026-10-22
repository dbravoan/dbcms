<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Contracts\Services\GeneralSettingsServiceInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use Modules\AiAssistant\Contracts\AiBrandProfileServiceInterface;

class AiBrandProfileService implements AiBrandProfileServiceInterface
{
    public function __construct(
        protected GeneralSettingsServiceInterface $generalSettings,
        protected ModuleSettingsServiceInterface $moduleSettings
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getProfile(): array
    {
        $siteName = (string) ($this->generalSettings->get('general.site_name') ?: 'Nuestra Empresa');
        $tagline = (string) ($this->generalSettings->get('general.site_tagline') ?: '');
        $companyName = (string) ($this->generalSettings->get('legal.company_name') ?: $siteName);

        return [
            'brand_name' => $siteName,
            'brand_tagline' => $tagline,
            'company_name' => $companyName,
            'brand_archetype' => (string) ($this->moduleSettings->get('ai_assistant', 'brand_archetype') ?: 'autoridad'),
            'brand_treatment' => (string) ($this->moduleSettings->get('ai_assistant', 'brand_treatment') ?: 'tu'),
            'brand_target_audience' => (string) ($this->moduleSettings->get('ai_assistant', 'brand_target_audience') ?: 'Clientes y usuarios que buscan soluciones confiables y de calidad.'),
            'brand_value_proposition' => (string) ($this->moduleSettings->get('ai_assistant', 'brand_value_proposition') ?: 'Excelencia en el servicio y resultados garantizados con el mejor equipo humano.'),
            'brand_preferred_terms' => (string) ($this->moduleSettings->get('ai_assistant', 'brand_preferred_terms') ?: 'calidad, rigor, compromiso, innovación, cercanía'),
            'brand_taboo_terms' => (string) ($this->moduleSettings->get('ai_assistant', 'brand_taboo_terms') ?: 'baratísimo, chapuza, milagroso, 100% garantizado sin esfuerzo'),
            'brand_cta_goals' => (string) ($this->moduleSettings->get('ai_assistant', 'brand_cta_goals') ?: 'Contactar con nosotros o solicitar más información.'),
            'brand_custom_directives' => (string) ($this->moduleSettings->get('ai_assistant', 'brand_custom_directives') ?: 'Sé conciso, empático y honesto. Evita la hipérbole y el relleno innecesario.'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function saveProfile(array $data): void
    {
        $keys = [
            'brand_archetype',
            'brand_treatment',
            'brand_target_audience',
            'brand_value_proposition',
            'brand_preferred_terms',
            'brand_taboo_terms',
            'brand_cta_goals',
            'brand_custom_directives',
        ];

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $this->moduleSettings->set('ai_assistant', $key, (string) $data[$key]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPresets(): array
    {
        return [
            'saas' => [
                'name' => 'SaaS B2B & Tecnología Empresarial',
                'description' => 'Tono riguroso, analítico, enfocado en eficiencia operativa, métricas de negocio y retorno de inversión.',
                'icon' => 'fa-solid fa-laptop-code',
                'brand_archetype' => 'tecnico',
                'brand_treatment' => 'tu',
                'brand_target_audience' => 'CTOs, Directores de Operaciones, Product Managers y fundadores de scaleups B2B que buscan automatización y fiabilidad técnica.',
                'brand_value_proposition' => 'Plataforma de alta disponibilidad que reduce costes de infraestructura y acelera el time-to-market sin generar deuda técnica.',
                'brand_preferred_terms' => 'escalabilidad, arquitectura desacoplada, resiliencia, observabilidad, latencia cero, ROI, alta disponibilidad',
                'brand_taboo_terms' => 'low cost, barato, chapuza, soluciones mágicas, fácil y rápido, sin esfuerzo',
                'brand_cta_goals' => 'Solicitar una demo personalizada o iniciar una prueba de concepto técnica gratuita.',
                'brand_custom_directives' => 'Fundamenta las afirmaciones con métricas y argumentos racionales. Evita superlativos vacíos como "la mejor del mundo". Prioriza la claridad técnica sobre el bombo publicitario.',
            ],
            'salud' => [
                'name' => 'Clínica Médica / Odontología / Salud',
                'description' => 'Tono empático, riguroso, sereno y tranquilizador. Máximo respeto deontológico sin promesas milagrosas.',
                'icon' => 'fa-solid fa-user-doctor',
                'brand_archetype' => 'autoridad',
                'brand_treatment' => 'usted',
                'brand_target_audience' => 'Pacientes y familias que buscan atención sanitaria de calidad, diagnósticos precisos y un trato cálido y humano.',
                'brand_value_proposition' => 'Equipo multidisciplinar de especialistas médicos con tecnología diagnóstica avanzada y enfoque centrado en la salud integral del paciente.',
                'brand_preferred_terms' => 'diagnóstico personalizado, bienestar del paciente, rigor clínico, tecnología médica, prevención, salud integral, confort',
                'brand_taboo_terms' => 'milagroso, cura garantizada al 100%, remedio infalible, dolor cero absoluto, baratísimo, oferta flash de salud',
                'brand_cta_goals' => 'Solicitar una primera consulta diagnóstica sin compromiso o contactar con el equipo médico.',
                'brand_custom_directives' => 'Nunca emitas diagnósticos definitivos; orienta siempre a una valoración clínica presencial. Mantén un trato de máximo respeto y deferencia utilizando siempre el pronombre de cortesía (usted).',
            ],
            'legal' => [
                'name' => 'Bufete Jurídico & Asesoría Fiscal',
                'description' => 'Tono solemne, riguroso, preciso y confidencial. Seguridad jurídica, defensa de intereses y tratamiento formal.',
                'icon' => 'fa-solid fa-scale-balanced',
                'brand_archetype' => 'institucional',
                'brand_treatment' => 'usted',
                'brand_target_audience' => 'Empresarios, directivos y particulares que requieren blindaje jurídico, planificación fiscal o litigación estratégica.',
                'brand_value_proposition' => 'Defensa jurídica estratégica y consultoría fiscal de alta precisión con absoluto compromiso de confidencialidad y solvencia técnica.',
                'brand_preferred_terms' => 'seguridad jurídica, jurisprudencia, marco normativo, defensa de intereses, asesoramiento preventivo, solvencia, rigor legal',
                'brand_taboo_terms' => 'ganamos siempre, juicio ganado seguro, abogados baratos, soluciones al margen de la ley, trucos para no pagar',
                'brand_cta_goals' => 'Agendar una consulta jurídica confidencial con un letrado especialista.',
                'brand_custom_directives' => 'Usa un registro formal y solemne. Utiliza exclusivamente el tratamiento de "usted". Enfatiza el análisis caso por caso y la prudencia jurisprudencial.',
            ],
            'retail' => [
                'name' => 'Ecommerce / Moda & Lifestyle',
                'description' => 'Tono cercano, aspiracional, vibrante y conectado a tendencias. Enfoque sensorial y dinamismo comercial.',
                'icon' => 'fa-solid fa-bag-shopping',
                'brand_archetype' => 'cercano',
                'brand_treatment' => 'tu',
                'brand_target_audience' => 'Compradores digitales que valoran el buen diseño, los materiales de calidad y una experiencia de compra impecable.',
                'brand_value_proposition' => 'Colecciones exclusivas con materiales seleccionados, envíos rápidos y una atención cercana para elevar tu estilo de vida.',
                'brand_preferred_terms' => 'tendencia, calidad atemporal, diseño exclusivo, confort, confección cuidada, estilo, sostenibilidad',
                'brand_taboo_terms' => 'saldo, mercancía barata, imitaciones, copia barata, cutre, liquidación por derribo',
                'brand_cta_goals' => 'Descubrir la nueva colección o disfrutar del envío gratuito en tu primer pedido.',
                'brand_custom_directives' => 'Utiliza un lenguaje fresco, evocador y sensorial. Apela a los sentidos, los acabados y la comodidad de uso diario.',
            ],
            'hosteleria' => [
                'name' => 'Gastronomía / Restauración & Turismo',
                'description' => 'Tono sensorial, acogedor, evocador y hospitalario. Pasión por el producto de proximidad y la experiencia del comensal.',
                'icon' => 'fa-solid fa-utensils',
                'brand_archetype' => 'cercano',
                'brand_treatment' => 'tu',
                'brand_target_audience' => 'Amantes de la buena mesa, familias, parejas y viajeros que buscan experiencias gastronómicas auténticas y momentos memorables.',
                'brand_value_proposition' => 'Cocina de autor con producto de proximidad, maridajes seleccionados y un ambiente cálido diseñado para disfrutar sin prisas.',
                'brand_preferred_terms' => 'producto de cercanía, tradición renovada, maridaje, sobremesa, ingredientes de temporada, experiencia gastronómica, hospitalidad',
                'brand_taboo_terms' => 'comida rápida, precocinado, microondas, franquicia masiva, relleno, raciones escasas',
                'brand_cta_goals' => 'Reservar mesa online o consultar la carta de temporada.',
                'brand_custom_directives' => 'Enfócate en los aromas, texturas y el origen de los ingredientes. Transmite calidez y el placer de compartir alrededor de la mesa.',
            ],
            'creativo' => [
                'name' => 'Agencia Creativa & Marketing Digital',
                'description' => 'Tono disruptivo, audaz, conceptual y directo. Foco en diferenciación de marca y resultados medibles.',
                'icon' => 'fa-solid fa-lightbulb',
                'brand_archetype' => 'disruptivo',
                'brand_treatment' => 'tu',
                'brand_target_audience' => 'Marcas y líderes de marketing inconformistas que quieren dejar de sonar como todos los demás y liderar su categoría.',
                'brand_value_proposition' => 'Estrategias de marca y creatividad radical que generan impacto cultural, posicionamiento magnético y conversión medible.',
                'brand_preferred_terms' => 'posicionamiento magnético, narrativa de marca, disrupción, impacto de negocio, storytelling, conversión, ventaja competitiva',
                'brand_taboo_terms' => 'sinergias holísticas, proactivo y dinámico, pensamiento fuera de la caja, consultoría 360, humo publicitario',
                'brand_cta_goals' => 'Hablemos de tu próximo proyecto o agenda una sesión de diagnóstico de marca.',
                'brand_custom_directives' => 'Ve al grano desde la primera frase. Desafía el status quo del sector. Cero clichés corporativos ni retórica hueca.',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function applyPreset(string $presetKey): bool
    {
        $presets = $this->getPresets();
        if (! isset($presets[$presetKey])) {
            return false;
        }

        $preset = $presets[$presetKey];
        $this->saveProfile($preset);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function buildBrandPromptSegment(string $locale = 'es'): string
    {
        $p = $this->getProfile();
        $lang = strtolower(substr(trim($locale), 0, 2));

        $archetypeDesc = match ($p['brand_archetype']) {
            'tecnico' => 'Técnico, analítico y preciso: prioriza datos objetivos, rigor formal y lógica operativa.',
            'autoridad' => 'Autoridad profesional y experto sectorial: sereno, resolutivo y fuente confiable de conocimiento.',
            'cercano' => 'Cercano, empático y accesible: conversa de igual a igual, transmitiendo calidez y escucha activa.',
            'disruptivo' => 'Disruptivo, audaz y directo: desafía lo convencional, oraciones breves y alta contundencia.',
            'institucional' => 'Institucional y solemne: diplomático, prudente, protocolario y de máxima seriedad.',
            'humoristico' => 'Ingenioso, desenfadado e inteligente: toques sutiles de humor fino sin restar profesionalidad.',
            default => 'Profesional equilibrado, claro y directo.',
        };

        $treatmentRules = match ($p['brand_treatment']) {
            'usted' => match ($lang) {
                'es' => <<<'TXT'
TRATAMIENTO GRAMATICAL OBLIGATORIO: TRATO FORMAL DE CORTESÍA ("USTED")
- Dirígete al interlocutor SIEMPRE de "usted" (verbos en 3ª persona: "consulte", "descubra", "haga", "su empresa", "le invitamos").
- Está TERMINANTEMENTE PROHIBIDO tutear al lector ("tú", "haz", "mira", "tu caso"). Cualquier forma de tuteo es un error crítico inaceptable.
TXT,
                'fr' => <<<'TXT'
TRAITEMENT GRAMMATICAL OBLIGATOIRE: VOUVOIEMENT ("VOUS")
- Utilisez TOUJOURS le vouvoiement de courtoisie ("vous", "votre", "consultez"). Le tutoiement ("tu") est formellement interdit.
TXT,
                'de' => <<<'TXT'
OBLIGATORISCHE ANREDEFORM: FORMELLES "SIE"
- Verwenden Sie AUSSCHLIESSLICH die Höflichkeitsform "Sie" / "Ihr". Das Duzen ("du") ist strengstens untersagt.
TXT,
                default => <<<'TXT'
MANDATORY FORMAL REGISTER:
- Maintain a strictly respectful, dignified, and formal address throughout all content.
TXT,
            },
            'nosotros' => match ($lang) {
                'es' => <<<'TXT'
TRATAMIENTO GRAMATICAL: PRIMERA PERSONA DEL PLURAL ("NOSOTROS / EL EQUIPO")
- Redacta desde la voz colectiva de la organización ("en nuestro equipo sabemos", "diseñamos", "ofrecemos").
TXT,
                default => <<<'TXT'
COLLECTIVE VOICE (WE / OUR TEAM):
- Speak from the collective perspective of the organization and team.
TXT,
            },
            default => match ($lang) {
                'es' => <<<'TXT'
TRATAMIENTO GRAMATICAL: TRATO DIRECTO Y CERCANO ("TÚ")
- Dirígete al lector de "tú" con naturalidad, empatía y cercanía profesional ("descubre", "prueba", "tu proyecto").
- Evita el distanciamiento excesivo de "usted", pero manteniendo siempre el máximo respeto.
TXT,
                default => <<<'TXT'
DIRECT AND ENGAGING ADDRESS:
- Address the reader directly and conversationally with professional warmth.
TXT,
            },
        };

        $brandHeader = "IDENTIDAD DE LA MARCA / ENTIDAD VHOST: {$p['brand_name']}";
        if (! empty($p['brand_tagline'])) {
            $brandHeader .= " — {$p['brand_tagline']}";
        }

        return <<<PROMPT
================================================================================
PERFIL DE MARCA, VOZ EDITORIAL Y BUYER PERSONA DE ESTA INSTANCIA
================================================================================
{$brandHeader}
Razón social o entidad de respaldo: {$p['company_name']}

1. ARQUETIPO DE VOZ Y PERSONALIDAD:
   {$archetypeDesc}

2. {$treatmentRules}

3. PÚBLICO OBJETIVO (BUYER PERSONA & A QUIÉN HABLAMOS):
   {$p['brand_target_audience']}

4. PROPUESTA DE VALOR Y SOLUCIÓN DIFERENCIAL:
   {$p['brand_value_proposition']}

5. VOCABULARIO Y CONCEPTOS PREFERENTES:
   Incorpora de forma natural y contextual términos afines a la marca como:
   "{$p['brand_preferred_terms']}".

6. TÉRMINOS Y CONCEPTOS VETADOS (TABÚES DE MARCA):
   Está ESTRICTAMENTE PROHIBIDO utilizar las siguientes expresiones, términos o conceptos tabú:
   "{$p['brand_taboo_terms']}".

7. OBJETIVOS DE CONVERSIÓN (LLAMADAS A LA ACCIÓN / CTA):
   {$p['brand_cta_goals']}

8. DIRECTIVAS PARTICULARES DE MARKETING Y COMUNICACIÓN:
   {$p['brand_custom_directives']}
PROMPT;
    }
}
