# airlock-php - Distributed locking with manners

[![Codacy Badge](https://app.codacy.com/project/badge/Grade/cdc12dbceac04dc8bbece4012222cd3d)](https://app.codacy.com/gh/clegginabox/airlock-php/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
[![Codacy Badge](https://app.codacy.com/project/badge/Coverage/cdc12dbceac04dc8bbece4012222cd3d)](https://app.codacy.com/gh/clegginabox/airlock-php/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_coverage)
![PHPStan Level 8](https://img.shields.io/badge/phpstan%20level-8%20of%209-green?style=flat-square&logo=php)

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=clegginabox_airlock-php&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=clegginabox_airlock-php)
[![Bugs](https://sonarcloud.io/api/project_badges/measure?project=clegginabox_airlock-php&metric=bugs)](https://sonarcloud.io/summary/new_code?id=clegginabox_airlock-php)
[![Code Smells](https://sonarcloud.io/api/project_badges/measure?project=clegginabox_airlock-php&metric=code_smells)](https://sonarcloud.io/summary/new_code?id=clegginabox_airlock-php)
[![Lines of Code](https://sonarcloud.io/api/project_badges/measure?project=clegginabox_airlock-php&metric=ncloc)](https://sonarcloud.io/summary/new_code?id=clegginabox_airlock-php)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=clegginabox_airlock-php&metric=coverage)](https://sonarcloud.io/summary/new_code?id=clegginabox_airlock-php)
[![Duplicated Lines (%)](https://sonarcloud.io/api/project_badges/measure?project=clegginabox_airlock-php&metric=duplicated_lines_density)](https://sonarcloud.io/summary/new_code?id=clegginabox_airlock-php)
[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=clegginabox_airlock-php&metric=reliability_rating)](https://sonarcloud.io/summary/new_code?id=clegginabox_airlock-php)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=clegginabox_airlock-php&metric=security_rating)](https://sonarcloud.io/summary/new_code?id=clegginabox_airlock-php)
[![Technical Debt](https://sonarcloud.io/api/project_badges/measure?project=clegginabox_airlock-php&metric=sqale_index)](https://sonarcloud.io/summary/new_code?id=clegginabox_airlock-php)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=clegginabox_airlock-php&metric=sqale_rating)](https://sonarcloud.io/summary/new_code?id=clegginabox_airlock-php)
[![Vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=clegginabox_airlock-php&metric=vulnerabilities)](https://sonarcloud.io/summary/new_code?id=clegginabox_airlock-php)

<img width="830" height="453" alt="airlock-php-red" src="https://github.com/user-attachments/assets/361fb9d2-00a4-4a11-b8cf-cde4fc951b9f" />

British-style queuing for your code and infrastructure. First come, first served. As it should be.

(Not to be confused with a message queue. Airlock doesn’t process messages — it just decides who’s coming in and who’s staying outside in the rain.)

> [!CAUTION]
> **Very Early Work in Progress** - This library is under active development and not yet production-ready. APIs will change, many implementations are stubs and test coverage is incomplete. Use at your own risk, contributions welcome.

## Documentation

Full documentation, guides and examples at **[clegginabox.github.io/airlock-php](https://clegginabox.github.io/airlock-php/)**.

See the [example app](https://airlock.clegginabox.co.uk/recipes) for real-world usage patterns.
