<?php

/**
 * Created by Cristian.
 * Date: 11/09/16 09:00 PM.
 */

namespace Reliese\Coders\Model;

use Illuminate\Support\Arr;
use Reliese\Meta\Blueprint;

class Config
{
    /**
     * @var array
     */
    protected $config;

    /**
     * ModelConfig constructor.
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        $this->config = $config;
    }

    /**
     * @param \Reliese\Meta\Blueprint $blueprint
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(Blueprint $blueprint, $key, $default = null)
    {
        if ($key === 'parent') {
            $tableConfig = Arr::get($this->config, $blueprint->table());
            if (is_array($tableConfig) && isset($tableConfig['parent'])) {
                return $tableConfig['parent'];
            }
        }

        $priorityKeys = [
            "@connections.{$blueprint->connection()}.{$blueprint->schema()}.{$blueprint->table()}.$key",
            "@connections.{$blueprint->connection()}.{$blueprint->table()}.$key",
            "@connections.{$blueprint->connection()}.{$blueprint->schema()}.$key",
            "@connections.{$blueprint->connection()}.$key",
            "{$blueprint->qualifiedTable()}.$key",
            "{$blueprint->table()}.$key",
            "{$blueprint->schema()}.$key",
            "*.$key",
        ];

        // Column casts are merged across every level of the resolution tree so
        // per-model blocks (e.g. 'users' => ['casts' => [...]]) layer on top of
        // the global ('*' => ['casts' => [...]]) defaults instead of replacing
        // them. More specific patterns win on collisions and are evaluated
        // first by the generator.
        if ($key === 'casts') {
            $casts = [];
            foreach ($priorityKeys as $priorityKey) {
                $level = Arr::get($this->config, $priorityKey);
                if (is_array($level)) {
                    $casts = $casts + $level;
                }
            }

            return empty($casts) ? $default : $casts;
        }

        foreach ($priorityKeys as $priorityKey) {
            $value = Arr::get($this->config, $priorityKey);

            if (!is_null($value)) {
                return $value;
            }
        }

        return $default;
    }
}
