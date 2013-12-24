Revisable
============

A **Laravel 4** base model that will allow for revisions to be made and tracked.

## Features

 - Easy to enable revisions tracking of any Eloquent models
 - Able to store revisions in the same table (requires soft-deletes), or a separate table
 - Able to switch between allowing a limited number of revisions and unlimited
 - Command to clear out any revisions that are no longer deemed active
 - Built on Ardent for automatic rule validation

## Quick Start

In the `require` key of `composer.json` file add the following

    "awjudd/l4-revisable": "*"

Run the Composer update command

    $ composer update

In your `config/app.php` add `'Awjudd\Revisable\RevisableServiceProvider'` to the end of the `$providers` array

    'providers' => array(

        'Illuminate\Foundation\Providers\ArtisanServiceProvider',
        'Illuminate\Auth\AuthServiceProvider',
        ...
        'Awjudd\Revisable\RevisableServiceProvider',

    ),

### Setup

#### Database

##### Same Table

In order to use the "same table" functionality the only thing that needs to be defined in your migrations is the "softDeletes" table option in order for us to maintain multiple copies of the information within the same table.

##### Different Table

A secondary table will need to be made in your database in order to handle having a separate table for the revisions.  This table be will hold any changes that are made and your base table will contain the latest "up-to-date" information.

#### Model

For any models that you would like to use the Revisable settings you should extend the "Revisable" base class and provide the following three members.

    <?php

    use Awjudd\Revisable\Revisable;

    class YourModel extends Revisable
    {

        /**
         * How many revivions are we keeping?
         * If this value is negative, then unlimited, otherwise however many
         * specified (0 = revisions are disabled)
         * 
         * @var integer
         */
        protected static $revisionCount = 0;

        /**
         * If the revisions are going to be stored in a separate table, then this
         * should be that table.  Otherwise NULL.
         * 
         * @var string
         */
        protected static $revisionTable = NULL;

        /**
         * All of the columns that one should be looking at to derive revision.
         * 
         * @var array
         */
        protected static $keyColumns = array();
    }

Extending this base class will override the save method and handle all of the heavy lifting that as to do with revision history.  It will also provide you with a few helper functions:
 - hasAlternateRevisionTable() - Whether or not there is a revision tabel
 - revisionsEnabled() - Are revisions enabled?
 - hasRevisions() - Are there any revisions?
 - getRevisions(array $columns = array('*')) - Retrieve any revisions that currently exist
 - removeExpired(array $where = array()) - Remove any of the expired revisions

### Clearing out Expired revisions

In an attempt to keep the application performant, it doesn't automatically purge any expired revisions.  You have to manually tell it to remove them.  To do this all you need to do is call the built in revisable:cleanup command.

    $ php artisan revisable:cleanup <model>

This will force anything that has expired to be deleted from the application

## License

Revisable is free software distributed under the terms of the MIT license

## Additional Information

Any issues, please [report here](https://github.com/awjudd/l4-revisable/issues)