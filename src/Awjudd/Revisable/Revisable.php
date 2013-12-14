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
                $keyName = $this->model->getKeyName();
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


    public function beforeSave()
    {
        // Check if revision history is enabled
        if(!$this->revisionsEnabled())
        {
            // They aren't enabled, so let them modify the row accordingly
            return true;
        }
    }

}