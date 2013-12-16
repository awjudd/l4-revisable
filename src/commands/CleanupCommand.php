<?php namespace Awjudd\Revisable;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CleanupCommand extends Command
{
    private $baseClassName = 'Awjudd\Revisable\Revisable';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'revisable:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes any clean up that is neeeded on the specified model.';

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        $app = app();

        return array(
            array('model', InputArgument::REQUIRED, 'The model that needs to be cleared.'),
        );
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $model = $this->argument('model');

        if(is_subclass_of($model, $this->baseClassName))
        {
            $model::removeExpired();
        }
        else
        {
            throw new \Exception('The specified class ('.$model.') does not extend '.$this->baseClassName);
        }
    }
}