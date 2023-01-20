# PrettyPHP

> The perfect PHP formatter doesn't exi...

## FAQ

### Why do we need another PHP formatter?

I wrote PrettyPHP because the alternatives I tested were all missing at least
one of the features I needed.

Here's what makes it different:

- It's opinionated
  - No configuration is required
  - Configurable options are deliberately limited

- It ignores previous formatting
  - Newlines adjacent to operators, separators and comments are preserved by
    default
  - All other whitespace is removed before formatting
  - Entire files are formatted in place

- It's CI-friendly
  - Installs via `composer require --dev` or direct download
  - Runs on Linux, macOS and Windows
  - MIT-licensed

- It's written in PHP
  - `token_get_all()` is used to parse code to tokens
  - Tokens are used to compare formatted code for equivalance with the original

- It handles arbitrarily complex nested expressions without obfuscating code

- It's optionally compliant with PSR-12 and other coding standards

- It's actively maintained

