---
layout: default
title: Installation
---

# Installation

## System Requirements

**PHP >= 8.1**  and `psr/log` package are required, but the latest stable versions of PHP and the log package are recommended.

The `symfony/console` and `symfony/process` packages are required if you want to use the CLI command.


## Installation

Use composer:

```
composer require {{ site.data.project.package }}:^{{ site.data.project.version }}
```

