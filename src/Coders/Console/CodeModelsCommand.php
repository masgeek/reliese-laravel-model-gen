<?php

namespace Reliese\Coders\Console;

use Illuminate\Console\Command;
use Reliese\Coders\Model\Factory;
use Illuminate\Contracts\Config\Repository;

class CodeModelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'code:models
                            {--s|schema= : The name of the MySQL database}
                            {--c|connection= : The name of the connection}
                            {--t|table= : The name of a specific table to generate}
                            {--view= : The name of a specific view to generate}
                            {--dry-run : List what would be generated without writing any files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse connection schema into models';

    /**
     * @var \Reliese\Coders\Model\Factory
     */
    protected $models;

    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * Create a new command instance.
     *
     * @param \Reliese\Coders\Model\Factory $models
     * @param \Illuminate\Contracts\Config\Repository $config
     */
    public function __construct(Factory $models, Repository $config)
    {
        parent::__construct();

        $this->models = $models;
        $this->config = $config;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->getConnection();
        $schema = $this->getSchema($connection);
        $table = $this->getTable();
        $view = $this->getView();
        $dryRun = (bool) $this->option('dry-run');

        $factory = $this->models
            ->on($connection)
            ->setOutput(function ($message) {
                $this->line($message);
            });

        if ($dryRun) {
            $factory->setDryRun(true);
            $this->warn('[dry-run] No files will be written.');
        }

        if ($table) {
            $factory->create($schema, $table);
            if (! $dryRun) {
                $this->info("Check out your model for $table");
            }
        } elseif ($view) {
            $factory->create($schema, $view);
            if (! $dryRun) {
                $this->info("Check out your model for view $view");
            }
        } else {
            $factory->map($schema);
            if (! $dryRun) {
                $this->info("Check out your models for $schema");
            }
        }
    }

    /**
     * @return string
     */
    protected function getConnection()
    {
        return $this->option('connection') ?: $this->config->get('database.default');
    }

    /**
     * @param string $connection
     *
     * @return string
     */
    protected function getSchema($connection)
    {
        return $this->option('schema') ?: $this->config->get("database.connections.$connection.database");
    }

    /**
     * @return string|null
     */
    protected function getTable()
    {
        return $this->option('table');
    }

    /**
     * @return string|null
     */
    protected function getView()
    {
        return $this->option('view');
    }
}
