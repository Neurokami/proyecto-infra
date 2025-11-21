<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function search(Request $request)
    {
        $products = [];

        if ($request->query("s")) {
            $products = Product::findByName($request->query("s"));
        } else {
            $products = Product::all();
        }

        return view("products", [
            'products' => $products
        ]);
    }
}
