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
     * @var Object
     */
    protected $old = NULL;

    /**
     * A boolean flag used internally in order to force a save to happen.
     * 
     * @var boolean
     */
    private $requireSave = FALSE;

    public function __construct(array $attributes = array())
    {
        // Call the parent's constructor
        parent::__construct($attributes);

        // Add the key column to the list of items to skip
        $this->keysToSkip[] = $this->getKeyName();
    }

    /**
     * Whether or not there is an alternate table for revisions.
     * 
     * @return boolean
     */
    public static function hasAlternateRevisionTable()
    {
        return static::$revisionTable !== NULL;
    }

    /**
     * Whether or not revisions are actually enabled.
     * 
     * @return boolean
     */
    public static function revisionsEnabled()
    {
        return static::$revisionCount <> 0;
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

        return $this->deriveRevisions()->count() > 0;
    }

    /**
     * Gets a list of revisions available for the specified model.
     * 
     * @param array $columnList The list of columns to retrieve
     * @return array
     */
    public function getRevisions($columnList = array('*'))
    {
        return $this->deriveRevisions()->get($columnList);
    }

    /**
     * Gets a single revision from the list.
     * 
     * @param int $revisionNumber The revision number to retrieve
     * @param array $columnList The list of columns to retrieve
     * @return array
     */
    public function getRevisionNumber($revisionNumber, $columnList = array('*'))
    {
        $query = $this->deriveRevisions();

        if($revisionNumber > 1)
        {
            // Skip ahead to where we need to be
            $query->skip($revisionNumber - 1)->take(1);
        }

        return $query->get($columnList)->first();
    }

    /**
     * Save the model to the database.
     * 
     * This is the primary call that will handle the saving of and committing of information to the database.
     * Within this method, it decides whether or not we will make a new copy of the model is made, or in the
     * case of a multi-table approach, the row will be inserted into the history table for the previous value
     * and update the base table with the new values.
     *
     * @param array   $rules
     * @param array   $customMessages
     * @param array   $options
     * @param Closure $beforeSave
     * @param Closure $afterSave
     *
     * @return bool
     * @see Ardent::forceSave()
     */
    public function save(array $rules = array(),
        array $customMessages = array(),
        array $options = array(),
        Closure $beforeSave = null,
        Closure $afterSave = null)
    {
        // Check if we are saving for the first time
        if(!isset($this->attributes[$this->getKeyName()]) || !$this->revisionsEnabled() || $this->requireSave)
        {
            // No key column identified, so it is new, or revisions are disabled
            return parent::save($rules, $customMessages, $options, $beforeSave, $afterSave);
        }

        // Revisions are enabled, so we need to act
        if($this->hasAlternateRevisionTable())
        {
            // The data is going into another table, so act on it

            // Add a new row into the new table for the current row
            $class = get_class($this);

            // Make a new instance of the class
            $instance = new $class;

            // Change the table it is pointing to
            $instance->table = static::$revisionTable;

            // Different table, so fill in the model
            foreach($this->attributes as $key => $value)
            {
                // Check if the $key is within the list of ones to skip
                if(!in_array($key, $this->keysToSkip))
                {
                    // It isn't, so set the value
                    $instance->$key = $this->original[$key];
                }
            }

            // Save the instance
            $instance->save();

            // Update the current row with the new information
            return parent::save($rules, $customMessages, $options, $beforeSave, $afterSave);
        }
        else
        {
            $this->requireSave = TRUE;

            // Delete the current instance
            $this->delete();

            $this->requireSave = FALSE;

            $class = get_class($this);
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
            $this->old = new $class();

            // Cycle through all of the attributes and set the override them
            // with the new instance's
            foreach($this->attributes as $key => $value)
            {
                $this->old[$key] = isset($this->original[$key]) ? $this->original[$key] : NULL;

                // Overwrite them with the current
                if(isset($updated->attributes[$key]))
                {
                    $this->attributes[$key] = $this->original[$key] = $updated->attributes[$key];
                }
            }
        }

        // Cancel the save operation
        return TRUE;
    }

    /**
     * Used in order to get rid of any expired revisions.
     * 
     * @param array $where Any filters to apply to the filters
     * @return boolean
     */
    public static function removeExpired(array $where = array())
    {
        // Check if we either have no revisions enabled, or it is set to infinite
        if(static::$revisionCount <= 0)
        {
            // Remove nothing
            return TRUE;
        }

        // Otherwise look for the expired revisions
        if( static::hasAlternateRevisionTable())
        {
            $query = \DB::table(static::$revisionTable);
        }
        else
        {
            $query = self::onlyTrashed();
        }

        // Were there any where clause filters?
        if(count($where)>0)
        {
            // There were, so add them in
            foreach($where as $key => $value)
            {
                $query->where($key, '=', $value);
            }
        }

        // Were there any columns that were defined as a "key column"
        if(count(static::$keyColumns) > 0)
        {
            // There were, so add in the group bys
            foreach(static::$keyColumns as $column)
            {
                $query->groupBy($column);
            }

            // Send back all of the key columns where we have more than the allowed
            $query->having(\DB::raw('count(1)'), '>', static::$revisionCount)
                ->addSelect(static::$keyColumns);
        }
        else
        {
            $query->where(\DB::raw('count(1)'), '>', static::$revisionCount);
        }

        // List of all of the ids affected by the revision count
        $ids = array();

        // Cycle through the list
        foreach($query->get() as $row)
        {
            // Otherwise look for the expired revisions
            if(static::hasAlternateRevisionTable())
            {
                $filter = \DB::table(static::$revisionTable);
            }
            else
            {
                $filter = self::onlyTrashed();
            }

            //
            foreach(static::$keyColumns as $column)
            {
                $filter->where($column, '=', $row->$column);
            }

            $filter->skip(static::$revisionCount)

                // Take as many as we need
                ->take(PHP_INT_MAX)

                // Grab anything that was soft deleted after the number we keep
                ->orderBy('created_at', 'desc')

                // Grab the ID column
                ->addSelect('id');

            // Cycle through all of the records
            foreach($filter->get() as $id)
            {
                $ids[] = $id->id;
            }
        }

        // Were there any records that fit the criteria
        if(count($ids) > 0)
        {
            // There were, so 
            if(static::hasAlternateRevisionTable())
            {
                $delete = \DB::table(static::$revisionTable);
            }
            else
            {
                $delete = self::onlyTrashed();
            }

            // Filter all of the selected
            $delete->whereIn('id', $ids);

            // Figure out how we should delete
            if(static::hasAlternateRevisionTable())
            {
                // Alternate table, so just delete it
                $delete->delete();
            }
            else
            {
                // Otherwise delete it
                $delete->forceDelete();
            }
        }

        return TRUE;
    }

    /**
     * Used in order to derive the actual query that is used to grab the revisions.
     * 
     * @return Illuminate\Database\Query\Builder
     */
    private function deriveRevisions()
    {
        $query = NULL;

        // There are, so look for any revisions
        if($this->hasAlternateRevisionTable())
        {
            $query = \DB::table(static::$revisionTable);
        }
        else
        {
            // Only grab the instances that were trashed
            $query = self::onlyTrashed();
        }

        // Cycle through all of the other respective columns
        foreach(static::$keyColumns as $column)
        {
            // Filter on the key values
            $query->where($column, '=', $this->$column);
        }

        // Check if we only want to keep a certain number of revisions
        if(static::$revisionCount > 0)
        {
            // Only grab the ones that fit
            $query->take($this->revisionCount);
        }

        // Return the query
        return $query->orderBy('created_at', 'desc');
    }

}