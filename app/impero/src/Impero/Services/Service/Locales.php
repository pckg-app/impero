<?php namespace Impero\Services\Service;

use Impero\Servers\Record\Task;

/**
 * Class Locales
 *
 * @package Impero\Services\Service
 */
class Locales extends AbstractService implements ServiceInterface
{

    /**
     * @var string
     */
    protected $service = 'locales';

    /**
     * @var string
     */
    protected $name = 'Locales';

    public function generateLocale($locale = 'en_US.UTF-8')
    {
        $task = Task::create('Generating locale ' . $locale);

        return $task->make(function() use ($locale) {
            $command = 'locale-gen ' . $locale;
            $this->exec($command);
        });
    }

}