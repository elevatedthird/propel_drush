# Propel
A drush command that downloads a SDC from the [propel-components](https://github.com/elevatedthird/propel-components) repository.

## Installation
Add to your composer.json's `repositories` key
```
{
  "type": "vcs",
  "url": "https://github.com/elevatedthird/propel_drush"
}
```
### Install package
`composer require elevatedthird/propel_drush:^1.0`

## Usage
```
# Install Propel
drush pm:enable propel

# Download the base stylesheet and all starter SDCs
propel:init

# Add an SDC from Prpel Components to your project
propel:add [SDC_NAME]
```
