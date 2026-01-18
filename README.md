# ProcessWireKickstart

ProcessWireKickstart is a single-file PHP installer/loader script for ProcessWire CMS.

## Quick Download

### Using curl

```bash
curl -OfsS https://raw.githubusercontent.com/markusthomas/ProcessWireKickstart/refs/heads/master/kickstart.php
```

### Using wget

```bash
wget -nv https://raw.githubusercontent.com/markusthomas/ProcessWireKickstart/refs/heads/master/kickstart.php
```

## Usage

After downloading, simply run the script in your web browser from your web server/development environment:

```text
# localhost
http://127.0.0.1/kickstart.php

# ddev
https://kickstart.ddev.site/kickstart.php
```

Depends on your setup. See instructions for your specific environment. Works with DDEV, Laragon, and other local development environments.

## Requirements

- PHP 7.4 or higher (PHP 8.0+ recommended)
- ZipArchive extension
- Write permissions on the directory
- CURL or allow_url_fopen enabled

## Features

- **One-file installer** - Single PHP script handles everything
- **Multi-language UI** - English, German, Spanish, French
- **Version selection** - Choose stable master, dev branch, or specific releases
- **Auto-download** - Downloads ProcessWire directly from GitHub
- **Auto-extraction** - Extracts and sets up files automatically
- **Browser language detection** - Automatically detects and sets UI language

## License

unknown
