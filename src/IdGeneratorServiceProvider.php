<?php namespace Starmoozie\IdGenerator;

use Illuminate\Support\ServiceProvider;

class IdGeneratorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        //
    }
    
    public function register()
    {
        $this->app->make('Starmoozie\IdGenerator\IdGenerator');
    }

}
