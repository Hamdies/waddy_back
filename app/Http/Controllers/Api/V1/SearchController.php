<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\CentralLogics\StoreLogic;
use App\CentralLogics\CategoryLogic;
use App\CentralLogics\ProductLogic;
use App\Http\Controllers\Controller;
use App\Services\Search\ItemSearch;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    public function get_searched_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        return response()->json(ItemSearch::search($request, $request->header('zoneId')), 200);
    }

    public function get_combined_data(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'list_type' => 'required|in:item,store',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        if (!$request->hasHeader('zoneId')) {
            $errors = [['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]];
            return response()->json(['errors' => $errors], 403);
        }
        $zone_id = $request->header('zoneId');
        $data_type = $request->query('data_type', 'all');
        $type = $request->query('type', 'all');
        $limit = $request->query('limit', 10);
        $offset = $request->query('offset', 1);

        $longitude = (float) $request->header('longitude') ?? 0;
        $latitude = (float) $request->header('latitude') ?? 0;
        $filter = $request->query('filter', '');
        $filter = $filter ? (is_array($filter) ? $filter : str_getcsv(trim($filter, "[]"), ',')) : '';
        $rating_count = $request->query('rating_count');


        if ($request->list_type == 'item') {
            $category_ids = $request->query('category_ids', '');
            $brand_ids = $request->query('brand_ids', '');
            $min_price = $request->query('min_price') == 0 ? 0.0001 : $request->query('min_price');
            $max_price = $request->query('max_price');
            $product_id = $request->query('product_id');

            switch ($data_type) {
                case 'searched':
                    return $this->get_searched_products($request);
                    break;
                case 'discounted':
                    $items = ProductLogic::discounted_products(zone_id:$zone_id,limit: $limit, offset:$offset,type: $type,category_ids: $category_ids,filter: $filter, min: $min_price,max: $max_price, rating_count:$rating_count,brand_ids: $brand_ids,search: $request->query('search')??null);
                    break;
                case 'brand':
                    $validator = Validator::make($request->all(), [
                        'brand_ids' => 'required',
                    ]);

                    if ($validator->fails()) {
                        return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                    }
                    $items = ProductLogic::brand_products($zone_id, $limit, $offset, $type, $category_ids, $filter, $min_price, $max_price, $rating_count, $brand_ids);
                    break;
                case 'new':
                    $items = ProductLogic::get_new_products($zone_id, $type, $min_price, $max_price, $product_id, $limit, $offset, $filter, $rating_count, $category_ids, $brand_ids);
                    break;
                case 'category':
                    $validator = Validator::make($request->all(), [
                        'category_ids' => 'required',
                    ]);

                    if ($validator->fails()) {
                        return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                    }

                    $items = CategoryLogic::category_products($category_ids, $zone_id, $limit, $offset, $type, $filter, $min_price, $max_price, $rating_count, $brand_ids);
                    break;
                default:
                    $items =  [
                        'total_size' => 0,
                        'limit' => $limit,
                        'offset' => $offset,
                        'products' => [],
                        'categories' => [],
                    ];
            }

            $items['products'] = Helpers::product_data_formatting($items['products'], true, false, app()->getLocale());
            return response()->json($items, 200);
        }


        switch ($data_type) {
            case 'searched':
                $validator = Validator::make($request->all(), ['name' => 'required']);
                if ($validator->fails()) {
                    return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                }

                $category_ids = $request['category_ids'] ? (is_array($request['category_ids']) ? $request['category_ids'] : json_decode($request['category_ids'],true )) : null;
                $paginator = StoreLogic::search_stores($request?->name, $zone_id, $request->category_id, $limit, $offset, $type, $longitude, $latitude, $filter, $rating_count, $category_ids);
                break;

            case 'discounted':

                $paginator = StoreLogic::get_discounted_stores($zone_id, $limit, $offset, $type, $longitude, $latitude, $filter, $rating_count);
                break;

            case 'category':
                $validator = Validator::make($request->all(), [
                    'category_ids' => 'required',
                ]);

                if ($validator->fails()) {
                    return response()->json(['errors' => Helpers::error_processor($validator)], 403);
                }
                $paginator = CategoryLogic::category_stores($request->category_ids, $zone_id, $limit, $offset, $type, $longitude, $latitude, $filter, $rating_count);
                break;

            default:
                $filter_data = $request->query('filter_data', 'all');
                $store_type = $request->query('store_type', 'all');
                $featured = $request->query('featured');
                $paginator = StoreLogic::get_stores($zone_id, $filter_data, $type, $store_type, $limit, $offset, $featured, $longitude, $latitude, $filter, $rating_count);
                break;
        }

        $paginator['stores'] = Helpers::store_data_formatting($paginator['stores'], true);
        return response()->json($paginator, 200);
    }
}
