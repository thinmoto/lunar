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
		$id = array_keys(request()->toArray())[0];

		$product = Product::find($id);
		$cloneProduct = $product->clone();

		// if($cloneProduct->attribute_data && $cloneProduct->attribute_data->get('name'))
		// {
		// 	$title = $cloneProduct->attribute_data->get('name')->getValue();
		//
		// 	foreach($title as $k => $v)
		// 	{
		// 		$title[$k] = $v .= ' Copy';
		// 	}
		//
		// 	$cloneProduct->attribute_data->get('name')->setValue($title);
		// 	$cloneProduct->save();
		// }

	    return response()->redirectToRoute('hub.products.show', $cloneProduct);
    }
}
