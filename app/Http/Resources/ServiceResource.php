<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
public function toArray($request)
{
    return [
        'id'          => $this->id,
        'name'        => $this->title,          // في القاعدة اسمه title
        'price'       => $this->price_start,    // في القاعدة اسمه price_start
        'location'    => $this->address,        // في القاعدة اسمه address
        'city'        => $this->city,           // أضيفي هذا أيضاً
        'category'    => $this->category_id,    // في القاعدة اسمه category_id
        
'main_image' => $this->images->isNotEmpty() 
    ? asset('storage/' . trim(basename($this->images->first()->image_path))) 
    : asset('images/default.jpg'),
        'all_images' => $this->images->map(function($img) {
            return filter_var($img->image_path, FILTER_VALIDATE_URL) 
                ? $img->image_path 
                : asset('storage/' .trim( basename($img->image_path)));
        }),
     //   'all_images'  => $this->images->map(fn($img) => asset('storage/' . $img->image_path)),
    ];
}
}
