# Changelog

## [0.16.0] - 2017-02-13
### Added
- Add auto update mechanism

## [0.15.2] - 2017-02-11
### Fixed
- Tag prefix not used on branch creation

## [0.15.1] - 2017-01-09
### Fixed
- Do a 'git pull' when merge a feature into release

## [0.14.2] - 2016-11-24
### Fixed
- Fix twgit feature merge-into-release failing

## [0.14.0] - 2016-11-21
### Fixed
- Remove the use of grep, sed and other shell commands in git command

## [0.13.2] - 2016-05-10
### Fixed
- Fix missing space for git merge --no-ff and make use of ordered placeholder

## [0.13.1] - 2016-04-28
### Fixed
- fix jira api response management : return true for all 200 codes

## [0.13.0] - 2016-02-03
### Fixed
- fix creation version on github

## [0.12.0] - 2016-01-28
### Added
- Add changelog ([issue 40](https://github.com/monsieurchico/php-twgit/issues/40))

## [0.11.0] - 2016-01-28
### Fixed
- Slash character is removed in twgit init commit message ([issue 39](https://github.com/monsieurchico/php-twgit/issues/39))

### Added
- Add tests on TextUtil class ([issue 37](https://github.com/monsieurchico/php-twgit/issues/37))
- Add Travis CI integration ([issue 38](https://github.com/monsieurchico/php-twgit/issues/38))

## [0.10.0] - 2016-01-28
### Fixed
- self-update command does not work when not in a working directory([issue 34](https://github.com/monsieurchico/php-twgit/issues/34))
