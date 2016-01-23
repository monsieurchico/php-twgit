Php TwGit
==========

#### [Homepage](http://monsieurchico.github.io/php-twgit/)

## TwGit

Twgit is a free and open source assisting tools for managing features, hotfixes and releases on Git repositories.
It provides simple, high-level commands to adopt the branching model describes in our documentation (see below).

This tools is largely inspired by [GitFlow](https://github.com/nvie/gitflow), but the workflow is different.

## Php TwGit

As a bash application, **Twgit** is not working properly on every shells of every OS. After several hours spent to make **twgit** fully compatible with zsh on MacOs (without success), I decided to rewrite the application **PHP**.

**Php Twgit** is a **Composer application** base on **Symfony 3 Console**.

## Requirements

- PHP Cli **>=5.5.9** with modules **curl** and the option **phar.readonly** set to **Off**.
- Git v1.7.2 _(2010)_ and above

## Installing

### Use the compiled version

```bash
$ wget http://monsieurchico.github.io/php-twgit/deploy/twgit.phar
$ sudo cp twgit.phar /usr/local/bin/twgit
```

### Get source code

```bash
$ git clone git@github.com:monsieurchico/php-twgit.git ~/php-twgit
$ cd ~/php-twgit
$ sh makefile.sh
$ sudo cp deploy/twgit.phar /usr/local/bin/twgit
```

## Configuring

On first execution, **php-twgit** will create a global configuration file : **~/.twgit/config.yml**.

On first execution in your git project directory (second if the real first execution wasn't in it), **php-twgit** will create a project configuration file as an exact copy of the global one : **./.twgit/config.yml**.

```yaml
parameters:
    command: twgit

    git:
        stable: 'master'
        origin: 'origin'

    workflow:
        prefixes: # prefixes for branches
            feature: 'feature-'
            release: 'release-'
            hotfix: 'hotfix-'
            demo: 'demo-'
            tag: 'v'

    connectors:
        enabled: ~ # set the connector you want to use
        jira:
            domain: 'pmdtech.atlassian.net'
            credentials: '' # base64(concat(user:password))
        redmine:
            domain: 'www.redmine.org'
            api_key: ~ # 40-byte hexadecimal string
        gitlab:
            domain: 'www.gitlab.com'
            user: ~
        github:
            repository: ~
            user: ~
            access_token: ~
        trello:
            domain: 'trello.com'
            application_key: ~ # check https://trello.com/docs/gettingstarted/index.html#getting-an-application-key
            token: ~ # check https://trello.com/docs/gettingstarted/index.html#getting-a-token-from-a-user

    features:
        subject_filename: 'features_subject'

    update:
         log_filename: 'last_update'
         nb_days: 2
         auto_check: true

    commit:
        first_commit_message: '[twgit] Init %s %s %s'
```

## Workflow

To see the available commands :

```bash
$ twgit
```

To see the availabe actions on a command :

```bash
$ twgit release
$ twgit feature
$ twgit demo ...
```

More documentation on the [Wiki](https://github.com/Twenga/twgit/wiki).

[![TwGit logo](https://github.com/Twenga/twgit/raw/stable/doc/logo-med.png)](http://twgit.twenga.com/) [Original TwGit links](https://github.com/Twenga/twgit/)
====

[Github](https://github.com/Twenga/twgit/)

[Homepage](http://twgit.twenga.com/)

[Wiki](https://github.com/Twenga/twgit/wiki)