# Accessibility Code Patterns

Illustrative patterns for accessibility review, linked from [SKILL.md](../SKILL.md).
Use dbCMS shared components when applicable. Markup-only examples still need
the stated behavior and CSS; they are not evidence of implemented CMS controls.

---

## Modal focus trap

Trap keyboard focus inside a modal dialog so Tab/Shift+Tab cycle through its focusable elements and Escape closes it.

```html
<button type="button" id="open-example">Open dialog</button>
<dialog id="example-dialog" aria-labelledby="example-title">
  <h2 id="example-title">Example dialog</h2>
  <form method="dialog"><button autofocus>Close</button></form>
</dialog>
```

```javascript
const exampleDialog = document.getElementById('example-dialog');
document.getElementById('open-example').addEventListener('click', () => {
  exampleDialog.showModal();
});
```

`showModal()` provides modal focus containment and Escape dismissal. Verify
focus restoration to the opener, initial focus and interaction in supported
browsers. In dbCMS place script in a bundled asset or a nonce-bearing script.

---

## Skip link

Allows keyboard users to bypass repetitive navigation and jump straight to main content.

```html
<body>
  <a href="#main-content" class="skip-link">Skip to main content</a>
  <header><!-- navigation --></header>
  <main id="main-content" tabindex="-1">
    <!-- main content -->
  </main>
</body>
```

```css
.skip-link {
  position: absolute;
  top: -40px;
  left: 0;
  background: #000;
  color: #fff;
  padding: 8px 16px;
  z-index: 100;
}

.skip-link:focus {
  top: 0;
}
```

---

## Error handling

Announce errors to screen readers and focus the first invalid field on submit.

```html
<form id="example-form" novalidate>
  <p id="error-summary" tabindex="-1"></p>
  <div class="field" aria-live="polite">
    <label for="email">Email</label>
    <input type="email" id="email"
           aria-invalid="true"
           aria-describedby="email-error">
    <p id="email-error" class="error" role="alert">
      Please enter a valid email address (e.g., name@example.com)
    </p>
  </div>
</form>
```

```javascript
const form = document.getElementById('example-form');
form.addEventListener('submit', (e) => {
  const firstError = form.querySelector('[aria-invalid="true"]');
  if (firstError) {
    e.preventDefault();
    firstError.focus();

    const errorSummary = document.getElementById('error-summary');
    errorSummary.textContent =
      `${form.querySelectorAll('[aria-invalid="true"]').length} errors found. Please fix them and try again.`;
    errorSummary.focus();
  }
});
```

---

## Form labels

Every input needs an associated label—either explicit (`for`/`id`) or implicit (wrapping `<label>`).

```html
<!-- ❌ No label association -->
<input type="email" placeholder="Email">

<!-- ✅ Explicit label -->
<label for="email">Email address</label>
<input type="email" id="email" name="email"
       autocomplete="email" required>

<!-- ✅ Implicit label -->
<label>
  Email address
  <input type="email" name="email" autocomplete="email" required>
</label>

<!-- ✅ With instructions -->
<label for="password">Password</label>
<input type="password" id="password"
       aria-describedby="password-requirements">
<p id="password-requirements">
  Must be at least 8 characters with one number.
</p>
```

---

## Dragging movements

Any action triggered by dragging must offer a single-pointer alternative (WCAG 2.5.7).

```html
<!-- ❌ Drag-only reorder -->
<ul class="sortable-list" draggable="true">
  <li>Item 1</li>
  <li>Item 2</li>
</ul>

<!-- ✅ Drag + button alternatives -->
<ul class="sortable-list">
  <li>
    <span>Item 1</span>
    <button aria-label="Move Item 1 up">↑</button>
    <button aria-label="Move Item 1 down">↓</button>
  </li>
  <li>
    <span>Item 2</span>
    <button aria-label="Move Item 2 up">↑</button>
    <button aria-label="Move Item 2 down">↓</button>
  </li>
</ul>
```

Also applies to sliders, map panning and colour pickers: provide an equivalent
click/tap alternative for 2.5.7. Keyboard access is a separate requirement and
by itself does not supply the required single-pointer alternative.

---

## ARIA tabs

Tabs require `role="tablist"`, `role="tab"`, and `role="tabpanel"` with proper `aria-selected`, `aria-controls`, and keyboard support.

```html
<div role="tablist" aria-label="Product information">
  <button role="tab" id="tab-1" aria-selected="true"
          aria-controls="panel-1">Description</button>
  <button role="tab" id="tab-2" aria-selected="false"
          aria-controls="panel-2" tabindex="-1">Reviews</button>
</div>
<div role="tabpanel" id="panel-1" aria-labelledby="tab-1">
  <!-- Panel content -->
</div>
<div role="tabpanel" id="panel-2" aria-labelledby="tab-2" hidden>
  <!-- Panel content -->
</div>
```

Arrow keys should move focus between tabs; the active tab receives `tabindex="0"` while inactive tabs use `tabindex="-1"`.

---

## Live regions and notifications

Use `aria-live` to announce dynamic content changes to screen readers without moving focus.

```html
<!-- Status updates (polite — waits for pause in speech) -->
<div aria-live="polite" aria-atomic="true" id="polite-announcer" class="status">
  <!-- Content updates announced to screen readers -->
</div>

<!-- Urgent alerts (assertive — interrupts) -->
<div id="assertive-announcer" role="alert" aria-live="assertive">
  <!-- Interrupts current announcement -->
</div>
```

```javascript
function showNotification(message, type = 'polite') {
  const container = document.getElementById(`${type}-announcer`);
  container.textContent = '';
  requestAnimationFrame(() => {
    container.textContent = message;
  });
}
```

Clear the container before writing to ensure the same message triggers a new announcement.

---

## Screen reader commands

Quick reference for the most common screen reader shortcuts.

| Action | VoiceOver (Mac) | NVDA (Windows) |
|--------|-----------------|----------------|
| Start/Stop | ⌘ + F5 | Ctrl + Alt + N |
| Next item | VO + → | ↓ |
| Previous item | VO + ← | ↑ |
| Activate | VO + Space | Enter |
| Headings list | VO + U, then arrows | H / Shift + H |
| Links list | VO + U | K / Shift + K |
