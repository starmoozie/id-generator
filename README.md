### How to

#### Generate increment custom id pada laravel

``` bash

# Tambahkan pada model

use Starmoozie\\IdGenerator\\IdGenerator;

public static function boot()
{
    parent::boot();
    self::creating(function ($model) {
        $config    = ['table' => $this->table, 'length' => 12, 'prefix' =>date('ymd')];
        $model->id = IdGenerator::generate($config);
    });
}

# Hasil
200124000001
200124000002
200124000003
200124000004
............
```

### SQL Support
- Mysql
- PostgreSQL