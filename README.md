Joomla cli
==========

[![Latest Stable Version](https://poser.pugx.org/picturae/joomla-cli/v/stable.svg)](https://packagist.org/packages/picturae/joomla-cli)
[![Build Status](https://travis-ci.org/picturae/joomla-cli.svg?branch=master)](https://travis-ci.org/picturae/joomla-cli)
[![Code Climate](https://codeclimate.com/github/picturae/joomla-cli/badges/gpa.svg)](https://codeclimate.com/github/picturae/joomla-cli)

Joomla-cli is a commandline tool to manage your Joomla websites.

For instance in our development / production environment we've build a [Phing](https://www.phing.info/) wrapper around it to deploy and install our Joomla! websites.

## Install Joomla

``` bash
vendor/bin/joomla-cli core:download --path=./public
```

--path defaults to joomla, can be whatever relative to the directory the command is executed.

--version defaults to latest stable e.g. 3.4.0, supports 3.3.* or exact version number.

With the --stable flag you can also install unstable versions e.g. 3.4.1-rc

## Install DB

``` bash
vendor/bin/joomla-cli core:install-db  --dbname="mydb" --dbuser="myuser" --dbpass="mypassword" --dbhost="localhost" --joomla-version="3.4.0"
```

This command is used to install the initial clean Joomla! database.

Other options:

--dbprefix to set the prefix for the Joomla! tables.


## Database updates

``` bash
vendor/bin/joomla-cli core:update-db --path=./public
```

This command uses the path to bootstrap the Joomla application and run the database migrations (if they are needed).

## Install languages

``` bash
vendor/bin/joomla-cli extension:language:install nl-NL --path=./public
```

This command uses the path to bootstrap the Joomla application and install the language.

## Thanks to…

* [Mark van Duijker](https://github.com/mvanduijker) for starting this tool!
