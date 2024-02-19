# Doxie Q Scanner file consumer
Connects to a Doxie Q scanner, pulls files from said scanner and finally removes them from the scanner.

---

## Index
- [API Documentation](#api-documentation)
- [Docker usage](#docker-usage)
- [Local Usage](#local-usage)
- [Running tests](#running-tests)

---

## API Documentation
[Doxie API Developer Guide](docs/DoxieAPIDeveloperGuide-Nov2017.pdf)

---

## Docker usage

### Pull image
```sh
docker image pull ghcr.io/jdenoc/doxie-consumer
```

### Run container
```shell
docker run --rm \
  --env SCANNER_HOST=doxie.scanner \
  --volume /host/machine/path/to/scans:/opt/doxie/scans \
  --volume /host/machine/path/to/logs:/var/log/doxie-consumer \
  docker-consumer
```

### Build image locally
```sh
docker image build --file .docker/Dockerfile --tag doxie-consumer .
```

---

## Local usage

### Requirements
- php 8.3
- php curl extension
- php phar extension

### Install dependencies 
```sh
composer validate && \
  composer install --no-dev --no-interaction
```

### Generate phar file
Before you can generate a phar file, you'll to make sure that some php.ini settings are set.  
Run the command `php -i | grep phar`.  
From the output, make sure that `phar.readonly` is _off_ and `phar.require_hash` is _on_.

```sh
vendor/bin/box validate && \
  vendor/bin/box compile
```

### Run process
```sh
export SCANNER_HOST=doxie.scanner
export DOWNLOAD_DIR=/path/to/downloads
export LOGS_DIR=/path/to/logs
php consumer.phar -vvv -- $SCANNER_HOST $DOWNLOAD_DIR >> $LOGS_DIR/$(date '+%Y%m%d').log
```

---

## Running tests

### Docker
**build image**
```sh
docker image build --file .docker/Dockerfile --target test --tag doxie-consumer:testing .
```

**run linter**
```sh
docker container run --rm doxie-consumer:testing vendor/bin/php-cs-fixer check --diff --stop-on-violation
```

**run tests**
```sh
docker container run --rm doxie-consumer:testing
```

### Local
**run linter**
```sh
vendor/bin/php-cs-fixer check --diff --stop-on-violation
```

**run tests**
```sh
vendor/bin/pest tests --stop-on-failure
```
