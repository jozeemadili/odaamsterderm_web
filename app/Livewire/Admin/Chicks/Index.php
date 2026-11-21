<?php

namespace App\Livewire\Admin\Chicks;

use App\Livewire\Portal\Chicks\Index as PortalChicks;
use Livewire\Attributes\Layout;

/** The same chick management for platform admins. */
#[Layout('layouts.admin', ['title' => 'Day-old chicks'])]
class Index extends PortalChicks {}
