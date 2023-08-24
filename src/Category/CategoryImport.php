<?php namespace Visiosoft\CatsModule\Category;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CategoryImport implements ToModel, WithHeadingRow
{
	public function model(array $row)
    {
        if ($row['title'] !== null) {
	        return new CategoryModel([
		        'name' => $row['title'],
		        'slug' => Str::slug($row['title']),
		        'parent_category_id' => $row['parent_id'] ?? null,
                'level' => $row['level'] ?? 0,
	        ]);
        }
    }
}
