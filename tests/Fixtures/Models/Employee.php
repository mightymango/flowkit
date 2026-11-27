<?php

namespace MightyMango\Flowkit\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use MightyMango\Flowkit\Concerns\HasFlowTriggers;

class Employee extends Model
{
    use HasFlowTriggers;

    protected $guarded = [];

    protected array $flowTriggers = ['created', 'status' => 'changed'];
}
