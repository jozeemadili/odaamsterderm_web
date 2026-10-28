<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A search word and what it points at: "vifaranga" → category C7010,
 * "kideri" → disease newcastle. target_code is the taxonomy code.
 */
#[Fillable(['term', 'language', 'target_type', 'target_code', 'notes'])]
class SearchKeyword extends Model {}
