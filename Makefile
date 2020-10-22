vendor/autoload.php:
	composer install

sniff: vendor/autoload.php
	composer cs

sniff-fix: vendor/autoload.php
	composer cs-fix

test: vendor/autoload.php
	composer test

coverage: vendor/autoload.php
	composer coverage
