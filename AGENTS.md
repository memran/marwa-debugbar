# Repository Guidelines

## Project Structure & Module Organization
`src/` contains the library code under the `Marwa\\DebugBar\\` namespace. Core entry points such as `DebugBar.php`, `Renderer.php`, and `CollectorManager.php` live at the top level; specialized behavior is split into `src/Collectors/`, `src/Core/`, `src/Contracts/`, and `src/Extensions/`. Example integration code lives in `example/` and `example/public/`. Tests belong in `tests/`; the directory exists but is currently sparse, so add new coverage there instead of embedding test code in examples.

## Build, Test, and Development Commands
Run `composer install` to install dependencies and generate the optimized autoloader. Use `composer test` for the full local gate: PHPUnit, PHPCS, and PHPStan. Use `composer run lint` to run PHPCS only, `composer run lint-fix` to apply automatic PHPCBF fixes where possible, and `composer run analyze` for static analysis. For a quick manual check of the example app, serve `example/public/` with PHP’s built-in server, for example `php -S 127.0.0.1:8000 -t example/public`.

## Coding Style & Naming Conventions
Follow PSR-12 and the existing `.editorconfig`: 4 spaces for PHP, LF line endings, and a final newline. Keep `declare(strict_types=1);` in PHP files where applicable; the fixer config enforces it for maintained code. Use short array syntax, single quotes unless interpolation is needed, and remove unused imports. Class names are PascalCase, interfaces/contracts stay singular (`Collector`), and new collectors should follow the `*Collector.php` suffix used in `src/Collectors/`.

## Testing Guidelines
PHPUnit is configured through `phpunit.xml` and loads `vendor/autoload.php`. Name new tests `*Test.php` and place them under `tests/` using the `Marwa\\DebugBar\\Tests\\` namespace. Cover collector behavior, rendered output, and edge cases around optional integrations. Run `composer test` before opening a PR; if coverage is incomplete, document the gap explicitly.

## Commit & Pull Request Guidelines
Recent history includes generic messages like `Fix issue`; improve on that with short, imperative subjects such as `Add session collector null guard`. Keep commits focused and easy to review. Pull requests should summarize behavior changes, list verification commands run, and link any related issue. Include screenshots or rendered HTML snippets when changing the debug bar UI.
