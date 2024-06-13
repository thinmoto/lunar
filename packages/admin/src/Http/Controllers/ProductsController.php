<?php

namespace Lunar\Hub\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Lunar\Hub\LunarHub;
use Lunar\Models\Product;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductsController extends Controller
{
    public function clone(Product $product)
    {
		$cloneProduct = $product->clone();

	    return response()->redirectToRoute('hub.products.show', $cloneProduct);
    }
}
