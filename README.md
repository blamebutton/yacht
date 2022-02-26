# Yacht

Yacht is a Laravel package that allows multiple Laravel Sail applications to run, at the same time!

It utilizes Traefik to proxy requests to each Laravel Sail instance.

## Installation

Yacht is published on Packagist. It can be installed using Composer:

```bash
composer require --dev blamebutton/yacht
```

## Usage

Yacht consists of two components, the system-wide proxy, and the customizable configuration in each Laravel project.

To start the system-wide proxy, the `yacht` command can be used:

### Start the Yacht proxy

```
vendor/bin/yacht start
```

### Stop the Yacht proxy

```
vendor/bin/yacht stop
```
