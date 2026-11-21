# PHP as the checks need it (`npm run check:php`): the command line, with pcov to measure which lines the tests run.
FROM php:8.5-cli
RUN pecl install pcov-1.0.12 && docker-php-ext-enable pcov
