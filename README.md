# Doxie Q Scanner file consumer
Connects to a Doxie Q scanner, pulls files from said scanner and finally removes them from the scanner.
It is recommended that you generate a `phar` file and upload that for usage, rather than the whole repo. Less files to manage.

## Requirements
- php >= 5.5
- php curl extension

## Generate phar file
Before you can generate a phar file, you'll to make sure that some php.ini settings are set.  
Run the command `php -i | grep phar`.  
From the output, make sure that `phar.readonly` is _off_ and `phar.require_hash` is _on_.

Once you've confirmed that the php.ini phar settings are correct, you can go through the following steps:
```sh
cp .env.example .env
# open .env file and update values
composer install --no-dev
php build.php         # this may take some time. grab yourself a drink
```
