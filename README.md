# Virtualmin Module

[![Build Status](https://travis-ci.org/blesta/module-virtualmin.svg?branch=master)](https://travis-ci.org/blesta/module-virtualmin) [![Coverage Status](https://coveralls.io/repos/github/blesta/module-virtualmin/badge.svg?branch=master)](https://coveralls.io/github/blesta/module-virtualmin?branch=master)

This is a module for Blesta that integrates with [Virtualmin](https://www.virtualmin.com/).

## Install the Module

1. You can install the module via composer:

    ```
    composer require blesta/virtualmin
    ```

2. OR upload the source code to a /components/modules/virtualmin/ directory within
your Blesta installation path.

    For example:

    ```
    /var/www/html/blesta/components/modules/virtualmin/
    ```

3. Log in to your admin Blesta account and navigate to
> Settings > Modules

4. Find the Virtualmin module and click the "Install" button to install it

5. You're done!

### Blesta Compatibility

|Blesta Version|Module Version|
|--------------|--------------|
|< v4.2.0|v1.0.0|
|>= v4.2.0|v1.1.0+|
|>= v4.9.0|v1.5.0+|
|>= v5.0.0|v1.7.0+|
