<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class RpRight extends Component
{
    public $value;

    public function __construct($value = 0)
    {
        $this->value = $value;
    }

    public function render(): View|Closure|string
    {
        return view('components.rp-right');
    }
}
