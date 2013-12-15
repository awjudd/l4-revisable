<?php namespace Awjudd\Revisable;

use LaravelBook\Ardent\Ardent;

abstract class Revisable extends Ardent
{
    /**
     * How many revivions are we keeping?
     * If this value is negative, then unlimited, otherwise however many
     * specified (0 = revisions are disabled)
     * 
     * @var integer
     */
    protected $revisionCount = 0;

    /**
     * If the revisions are going to be stored in a separate table, then this
     * should be that table.  Otherwise NULL.
     * 
     * @var string
     */
    protected $revisionTable = NULL;

    /**
     * All of the columns that one should be looking at to derive revision.
     * 
     * @var array
     */
    protected $keyColumns = array();

    /**
     * An array of all of the keys that the model shouldn't copy over when
     * duplicating.
     *
     * @var array
     */
    protected $keysToSkip = array(
        'created_at',
        'updated_at',
        'deleted_at',
    );

    /**
     * The complete previous version of the object.
     *
     * Object
     */
    protected $old = NULL;

    public function __construct()
    {
        parent::__construct();

        // Add the key column to the list of items to skip
        $this->keysToSkip[] = $this->getKeyName();
    }

    /**
     * Whether or not there is an alternate table for revisions.
     * 
     * @return boolean
     */
    public function hasAlternateRevisionTable()
    {
        return $this->revisionTable !== NULL;
    }

    /**
     * Whether or not revisions are actually enabled.
     * 
     * @return boolean
     */
    public function revisionsEnabled()
    {
        return $this->revisionCount == 0;
    }

    /**
     * Whether or not there are current revisions.
     * 
     * @return boolean
     */
    public function hasRevisions()
    {
        // Check if the revisions are enabled
        if(!$this->revisionsEnabled())
        {
            // They aren't so there are no revisions
            return FALSE;
        }

        return $this->getRevisions()->count() > 0;
    }

    public function getRevisions($columnList = array('*'))
    {
        // There are, so look for any revisions
        if($this->hasAlternateRevisionTable())
        {
            // They have an another table where the revisions are stored,
            // so look it up.
        }
        else
        {
            // Only grab the things that 
            $query = NULL;

            // Check if soft deletes are enabled
            if($this->softDelete)
            {
                // They are, so grab all of the trashed items
                $query = self::onlyTrashed();
            }
            else
            {
                // They aren't, so make sure we remove the current element

                // Grab the key column name
                $keyName = $this->getKeyName();
                $query = self::where($keyName, '<>', $this->attributes[$keyName]);
            }

            // Cycle through all of the other respective columns
            foreach($this->keyColumns as $column)
            {
                // Filter on the key values
                $query->where($column, '=', $this->$column);
            }

            // Return the query
            return $query->take($this->revisionCount)->orderBy('created_at', 'desc')->get($columnList);
        }
    }


    public function beforeSave($model)
    {
        // Check if we are saving for the first time
        if(!isset($this->attributes[$this->getKeyName()]))
        {
            // No key column identified, so it is new
            return TRUE;
        }

        // Check if revision history is enabled
        if(!$this->revisionsEnabled())
        {
            // They aren't enabled, so let them modify the row accordingly
            return TRUE;
        }

        // Revisions are enabled, so we need to act
        if($this->hasAlternateRevisionTable())
        {
            // The data is going into another table, so act on it
        }
        else
        {
            $class = get_class($model);
            $updated = new $class;

            // Same table, so make a new version of the model
            foreach($this->attributes as $key => $value)
            {
                // Check if the $key is within the list of ones to skip
                if(!in_array($key, $this->keysToSkip))
                {
                    // It isn't, so set the value
                    $updated->$key = $value;
                }
            }

            // Save the new object
            $updated->save();

            // Create a new instance of the object
            $this->old = new $class;

            // Cycle through all of the attributes and set the override them
            // with the new instance's
            foreach($this->attributes as $key => $value)
            {
                // Copy the values into the new instance
                $this->old->attributes[$key] = $value;

                // Overwrite them with the current
                if(isset($updated->attributes[$key]))
                {
                    $this->attributes[$key] = $updated->attributes[$key];
                }
            }

            // Delete the old object
            $this->old->delete();
        }

        // Cancel the save operation
        return FALSE;
    }

    public function save(array $attributes = array('*'))
    {

    }

    public function afterSave()
    {
        // Kick off the post-save event if necessary
        //$this->postSave($this->updatedObject);
    }

    public function postSave($updated)
    {
        throw new \Exception('Not Implemented');
    }

}