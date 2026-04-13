# Contributing to WP-AutoInsight

Thank you for your interest in contributing to WP-AutoInsight! This project is a production WordPress plugin used by 1,000+ active installations. We prioritize code quality, security, and adherence to WordPress standards.

---

## 🚀 Getting Started

### 1. Prerequisites
- **WordPress 6.8+** (Development environment)
- **PHP 7.4+**
- **Composer** (for dependencies and linting)

### 2. Setup
1. Clone the repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/phalkmin/wp-autoinsight.git
   ```
2. Install PHP dependencies:
   ```bash
   composer install
   ```

### 3. Local Configuration
For security and convenience, define your API keys in `wp-config.php` rather than the admin UI:
```php
define('OPENAI_API', 'your-key-here');
define('CLAUDE_API', 'your-key-here');
define('GEMINI_API', 'your-key-here');
define('PERPLEXITY_API', 'your-key-here');
```

---

## 🛠 Development Workflow

### Coding Standards
We follow the **WordPress Coding Standards (WPCS)**. A custom `phpcs.xml.dist` is provided to enforce these rules.

- **Linting:** `phpcs` (or `vendor/bin/phpcs`)
- **Auto-fixing:** `phpcbf` (or `vendor/bin/phpcbf`)
- **PHP:** Use tabs for indentation, `snake_case` for functions, and the `abcc_` prefix for all plugin-specific functions.
- **JS/CSS:** Use WordPress native UI classes where possible.

### Key Architectural Patterns
Before adding code, please familiarize yourself with these core patterns:

1. **Provider Registry (`includes/providers.php`):** All AI provider definitions live here. Do not hardcode provider strings elsewhere.
2. **Settings Schema (`includes/settings.php`):** All plugin options and defaults are declared in `abcc_get_settings_schema()`.
3. **Job Queue (`includes/class-abcc-job.php`):** Async operations must use the background job queue via `abcc_queue_generation_job()`.
4. **AJAX Handlers:** Follow the security pattern: Check nonces, validate permissions (`current_user_can`), and sanitize all inputs.

### Important Constraints
- **Browser Storage:** `localStorage` and `sessionStorage` are **forbidden** (they break in WordPress iframe environments).
- **Internationalization:** All user-facing strings must use the `'automated-blog-content-creator'` text domain.
- **Database:** Use `update_option()` or `wp_insert_post()`. Avoid raw SQL; if necessary, use `$wpdb->prepare()`.

### Technical Integrity & "Vibe-Coding"
We maintain a high bar for technical rigor. 
- **No "Vibe-Coding":** We do not accept AI-generated contributions that haven't been thoroughly manually verified, tested, and understood by the author. 
- **Ownership:** "Vibe-coded" PRs—those that may look correct at a glance but fail to adhere to WordPress standards, introduce subtle bugs, or lack proper error handling—will be closed. You are responsible for the correctness and architectural fit of every line of code you submit.
- **Verification:** Empirical reproduction of bugs and automated tests for new features are mandatory. "It feels like it should work" is not a valid verification strategy.

---

## 🧪 Testing

We use a custom regression test suite that does not require a full WordPress installation for core logic testing.

- **Run Tests:** `php tests/run.php`
- **Adding Tests:** Create or update a `*Test.php` file in the `tests/` directory. Register new cases with `abcc_test()`.

**Verification Checklist:**
1. Does it work with ALL configured AI providers?
2. Is input sanitized and output escaped?
3. Is nonce verification present?
4. Will it pass WPCS linting?
5. Does it follow KISS/YAGNI principles?

---

## 📬 Submitting Changes

1. **Branching:** Create a descriptive branch name (e.g., `feature/add-new-model` or `fix/ajax-error`).
2. **Commits:** Use short, imperative messages (e.g., `Fix: sanitize keyword input` or `Add: support for o3-mini`).
3. **Pull Requests:**
   - Provide a concise summary of the change.
   - List affected files.
   - Include manual test steps.
   - Attach screenshots/GIFs for any UI changes.

---

## 🔐 Security

**Never commit API keys or sensitive credentials.**

If you discover a security vulnerability, please do not open a public issue. Instead, email the lead maintainer at `phalkmin@protonmail.com`.

---

## ⚖️ License
By contributing, you agree that your contributions will be licensed under the **GPLv2 or later**.
