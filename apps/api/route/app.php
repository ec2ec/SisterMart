<?php

use app\middleware\AdminAclGate;
use app\middleware\AdminAuth;
use app\middleware\MemberAuth;
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});

Route::get('hello/:name', 'index/hello');

/** 健康检查：规范路径 + 运维兼容别名 */
Route::get('api/v1/health', 'Health/index');
Route::get('health', 'Health/index');
Route::get('api/health', 'Health/index');

/** M2 会员资源 */
Route::post('api/v1/verification-codes', 'api.VerificationCodes/store');
/** M30 綁定：須排在 POST members 之前，避免被註冊路由前綴吞掉 */
Route::post('api/v1/member-bindings/code', 'api.Members/bindCode')->middleware(MemberAuth::class);
Route::post('api/v1/member-bindings', 'api.Members/bind')->middleware(MemberAuth::class);
Route::post('api/v1/members', 'api.Members/store');
Route::patch('api/v1/member-passwords', 'api.MemberPasswords/update');
Route::post('api/v1/member-sessions', 'api.MemberSessions/store');
Route::get('api/v1/tabbar', 'api.Tabbar/index');
/** 購物車頂部信任標籤（公開） */
Route::get('api/v1/cart/trust-tags', 'api.CartTrustTags/index');

/** M3 公開商品與文章（帶參數路由須排在集合路由之前，避免前綴誤匹配） */
/** 上傳圖公開讀取（共享主機常無 /storage 靜態別名；query 形式兼容 nginx） */
Route::get('api/v1/media/<path>', 'api.Media/show')->pattern(['path' => '.*']);
Route::get('api/v1/media', 'api.Media/show');
/** 別名：部分主機對 media 路徑異常時可走 files */
Route::get('api/v1/files/<path>', 'api.Media/show')->pattern(['path' => '.*']);
Route::get('api/v1/files', 'api.Media/show');

Route::get('api/v1/categories', 'api.Categories/index');
Route::get('api/v1/products/:id', 'api.Products/read');
/** M28 首頁推薦（可選 Bearer：猜你喜歡） */
Route::get('api/v1/home/recommendations', 'api.HomeRecommendations/index');
/** M27 訂閱（可匿名；帶 Bearer 則綁定會員） */
Route::post('api/v1/newsletter/subscribe', 'api.Newsletter/subscribe');
Route::get('api/v1/products', 'api.Products/index');
Route::get('api/v1/articles/:code', 'api.Articles/read');
Route::get('api/v1/home/banners', 'api.HomeBanners/index');
Route::get('api/v1/home/ops', 'api.HomeOps/index');
/** M29 首頁商品展示區 */
Route::get('api/v1/home/sections/:code/products', 'api.HomeSections/products');
Route::get('api/v1/home/sections', 'api.HomeSections/index');

/** M6 公開：領券中心列表、等級檔位 */
Route::get('api/v1/coupons/claimable', 'api.Coupons/claimable');
Route::get('api/v1/member-levels', 'api.Assets/levelsPublic');

/** M11 秒殺 + M12 積分商城（真實）+ M16 分享瀏覽（真實） */
Route::get('api/v1/seckill/sessions/:id', 'api.Seckill/read')->pattern(['id' => '[A-Za-z0-9_-]+']);
Route::get('api/v1/seckill/sessions', 'api.Seckill/index');
Route::get('api/v1/seckill/products/:productId', 'api.Seckill/product')->pattern(['productId' => '[A-Za-z0-9_-]+']);
Route::get('api/v1/points-mall/products/:id', 'api.PointsMall/read');
Route::get('api/v1/points-mall/products', 'api.PointsMall/index');
Route::post('api/v1/share-views', 'api.ShareViews/store');

/** M10 公開：支付渠道異步回調（驗簽由渠道適配層負責） */
Route::post('api/v1/payment-callbacks/:channel', 'api.PaymentCallbacks/store');

Route::group('api/v1', function () {
    Route::get('members/me/level', 'api.Assets/level');
    Route::get('members/me', 'api.Members/me');
    Route::patch('members/me', 'api.Members/updateMe');
    Route::delete('member-sessions/current', 'api.MemberSessions/destroy');

    Route::get('addresses', 'api.Addresses/index');
    Route::post('addresses', 'api.Addresses/store');
    Route::put('addresses/:id', 'api.Addresses/update');
    Route::delete('addresses/:id', 'api.Addresses/destroy');

    Route::get('cart', 'api.Cart/show');
    Route::post('cart/items', 'api.Cart/storeItem');
    Route::patch('cart/items/:id', 'api.Cart/updateItem');
    Route::post('cart/delete-items', 'api.Cart/destroyItems');
    Route::put('cart/check-all', 'api.Cart/checkAll');

    Route::post('checkout/quote', 'api.Orders/quote');
    Route::get('orders/counts', 'api.Orders/counts');
    Route::get('orders/:orderId/shipment', 'api.Shipments/read');
    Route::post('orders/:id/confirm-receipt', 'api.Receipts/confirm');
    Route::post('orders/:id/cancel', 'api.Orders/cancel');
    Route::post('orders/:id/payments', 'api.Orders/pay');
    Route::get('orders/:id', 'api.Orders/read');
    Route::get('orders', 'api.Orders/index');
    Route::post('orders', 'api.Orders/store');
    Route::post('payments/:id/mock-confirm', 'api.Payments/mockConfirm');

    Route::get('aftersales/:id', 'api.Aftersales/read');
    Route::post('aftersales/:id/cancel', 'api.Aftersales/cancel');
    Route::get('aftersales', 'api.Aftersales/index');
    Route::post('aftersales', 'api.Aftersales/store');
    Route::post('uploads/aftersale-evidence', 'api.Uploads/aftersaleEvidence');
    Route::post('uploads/review-image', 'api.Uploads/reviewImage');
    Route::post('uploads/avatar', 'api.Uploads/avatar');
    Route::post('reviews/:id/follow', 'api.Reviews/follow');
    Route::post('reviews', 'api.Reviews/store');

    /** M28 收藏 */
    Route::get('favorites/codes', 'api.Favorites/codes');
    Route::post('favorites/toggle', 'api.Favorites/toggle');
    Route::delete('favorites/:id', 'api.Favorites/destroy');
    Route::get('favorites', 'api.Favorites/index');
    Route::post('favorites', 'api.Favorites/store');

    /** M6 資產 */
    Route::post('coupons/:id/claims', 'api.Coupons/claim');
    Route::get('my-coupons', 'api.Coupons/mine');
    Route::get('point-logs', 'api.Assets/pointLogs');
    Route::get('balance-logs', 'api.Assets/balanceLogs');

    /** M26 瀏覽足跡 */
    Route::delete('browse-history/:id', 'api.BrowseHistory/destroy');
    Route::delete('browse-history', 'api.BrowseHistory/clear');
    Route::get('browse-history', 'api.BrowseHistory/index');

    /** M27 訂閱狀態（本人） */
    Route::get('newsletter/me', 'api.Newsletter/me');

    /** M14 簽到 + M13 分銷 + M15 站內消息 */
    Route::get('checkins/calendar', 'api.Checkins/calendar');
    Route::post('checkins', 'api.Checkins/store');
    Route::get('distribution/overview', 'api.Distribution/overview');
    Route::get('distribution/downlines', 'api.Distribution/downlines');
    Route::get('distribution/commissions', 'api.Distribution/commissions');
    Route::get('distribution/orders', 'api.Distribution/orders');
    Route::get('distribution/ranking', 'api.Distribution/ranking');
    Route::get('distribution/poster', 'api.Distribution/poster');
    Route::post('distribution/withdrawals', 'api.Distribution/withdraw');
    Route::get('messages/unread-count', 'api.Messages/unread');
    Route::post('messages/read', 'api.Messages/read');
    Route::get('messages', 'api.Messages/index');
})->middleware(MemberAuth::class);

/** 關單 / 自動收貨任務可無會員令牌（本地驗收手動觸發） */
Route::post('api/v1/jobs/close-expired-orders', 'api.TradeJobs/closeExpired');
Route::post('api/v1/jobs/auto-receive-orders', 'api.FulfillmentJobs/autoReceive');

/** M2 后台资源 */
Route::post('api/v1/admin/sessions', 'admin.Sessions/store');

Route::group('api/v1/admin', function () {
    Route::delete('sessions/current', 'admin.Sessions/destroy');
    Route::get('admins/me', 'admin.Admins/me');
    Route::get('admins', 'admin.Admins/index');
    Route::post('admins', 'admin.Admins/store');
    Route::put('admins/:id', 'admin.Admins/update');
    Route::get('tabbar', 'admin.Tabbar/index');
    Route::put('tabbar', 'admin.Tabbar/update');
    Route::get('cart-trust-tags', 'admin.CartTrustTags/index');
    Route::post('cart-trust-tags', 'admin.CartTrustTags/store');
    Route::put('cart-trust-tags/:id', 'admin.CartTrustTags/update');
    Route::delete('cart-trust-tags/:id', 'admin.CartTrustTags/destroy');

    Route::get('categories', 'admin.Categories/index');
    Route::post('categories', 'admin.Categories/store');
    Route::put('categories/:id', 'admin.Categories/update');

    Route::get('products/:id', 'admin.Products/read');
    Route::put('products/:id', 'admin.Products/update');
    Route::get('products', 'admin.Products/index');
    Route::post('products', 'admin.Products/store');

    Route::get('badges', 'admin.Badges/index');
    Route::post('badges', 'admin.Badges/store');
    Route::put('badges/:id', 'admin.Badges/update');

    Route::get('service-tags', 'admin.ServiceTags/index');
    Route::post('service-tags', 'admin.ServiceTags/store');
    Route::put('service-tags/:id', 'admin.ServiceTags/update');

    Route::get('attrs', 'admin.Attrs/index');
    Route::post('attrs', 'admin.Attrs/store');
    Route::put('attrs/:id', 'admin.Attrs/update');
    Route::post('attrs/:id/values', 'admin.Attrs/storeValue');
    Route::put('attrs/:id/values/:valueId', 'admin.Attrs/updateValue');
    Route::delete('attrs/:id/values/:valueId', 'admin.Attrs/destroyValue');

    Route::get('articles/:code', 'admin.Articles/read');
    Route::put('articles/:id', 'admin.Articles/update');
    Route::get('articles', 'admin.Articles/index');
    Route::post('articles', 'admin.Articles/store');

    Route::get('home-banners', 'admin.HomeBanners/index');
    Route::post('home-banners', 'admin.HomeBanners/store');
    Route::put('home-banners/:id', 'admin.HomeBanners/update');
    Route::delete('home-banners/:id', 'admin.HomeBanners/destroy');
    Route::post('uploads/banner', 'admin.HomeBanners/upload');
    Route::post('uploads/image', 'admin.Uploads/image');
    Route::get('home-ops', 'admin.HomeOps/read');
    Route::put('home-ops', 'admin.HomeOps/update');
    /** M29 展示區 */
    Route::get('home-sections', 'admin.HomeSections/index');
    Route::post('home-sections', 'admin.HomeSections/store');
    Route::put('home-sections/:id', 'admin.HomeSections/update');
    Route::delete('home-sections/:id', 'admin.HomeSections/destroy');
    Route::put('home-sections/:id/products', 'admin.HomeSections/products');

    Route::get('settings/sold-display', 'admin.ShopSettings/soldDisplay');
    Route::put('settings/sold-display', 'admin.ShopSettings/updateSoldDisplay');
    Route::get('settings/shop', 'admin.ShopSettings/shop');
    Route::put('settings/shop', 'admin.ShopSettings/updateShop');

    Route::post('orders/:orderId/shipment-nodes', 'admin.Shipments/addNode');
    Route::get('orders/:orderId/shipment', 'admin.Shipments/read');
    Route::post('orders/:orderId/shipments', 'admin.Shipments/ship');
    /** M31 給買家留言 */
    Route::post('orders/:id/buyer-messages', 'admin.Orders/buyerMessage');
    Route::get('orders/:id', 'admin.Orders/read');
    Route::get('orders', 'admin.Orders/index');
    Route::post('jobs/close-expired-orders', 'admin.Orders/closeExpired');
    Route::post('jobs/auto-receive-orders', 'api.FulfillmentJobs/autoReceive');

    Route::get('aftersales/:id', 'admin.Aftersales/read');
    Route::post('aftersales/:id/review', 'admin.Aftersales/review');
    Route::post('aftersales/:id/mark-returned', 'admin.Aftersales/markReturned');
    Route::post('aftersales/:id/force-balance', 'admin.Aftersales/forceBalance');
    Route::get('aftersales', 'admin.Aftersales/index');

    /** M23/M25 評價後台：隱藏 / 回覆 / 審核 */
    Route::get('reviews/:id', 'admin.Reviews/read');
    Route::post('reviews/:id/visibility', 'admin.Reviews/visibility');
    Route::post('reviews/:id/reply', 'admin.Reviews/reply');
    Route::post('reviews/:id/moderate', 'admin.Reviews/moderate');
    Route::get('reviews', 'admin.Reviews/index');

    /** M6 營銷與會員資產 */
    Route::get('coupons', 'admin.Coupons/index');
    Route::post('coupons', 'admin.Coupons/store');
    Route::put('coupons/:id', 'admin.Coupons/update');
    Route::get('points/settings', 'admin.Points/settings');
    Route::put('points/settings', 'admin.Points/updateSettings');
    /** M32 積分商城後台 */
    Route::get('points-mall/items', 'admin.PointsMall/index');
    Route::post('points-mall/items', 'admin.PointsMall/store');
    Route::put('points-mall/items/:id', 'admin.PointsMall/update');
    Route::delete('points-mall/items/:id', 'admin.PointsMall/destroy');

    /** M21 秒殺後台 CRUD */
    Route::get('seckill/sessions/:id', 'admin.Seckill/read');
    Route::put('seckill/sessions/:id', 'admin.Seckill/update');
    Route::get('seckill/sessions', 'admin.Seckill/index');
    Route::post('seckill/sessions', 'admin.Seckill/store');
    Route::get('member-levels', 'admin.Levels/index');
    Route::put('member-levels/:id', 'admin.Levels/update');
    Route::get('members/:id', 'admin.Members/read');
    Route::post('members/:id/asset-adjustments', 'admin.Members/adjust');
    Route::get('members', 'admin.Members/index');

    /** M13 提現審核 + M20 分銷後台只讀 */
    Route::get('withdrawals', 'admin.Withdrawals/index');
    Route::post('withdrawals/:id/review', 'admin.Withdrawals/review');
    Route::get('distribution/ranking', 'admin.Distribution/ranking');
    Route::get('distribution/commissions', 'admin.Distribution/commissions');
    Route::get('distribution/products', 'admin.Distribution/products');

    /** M15/M27 系統消息 */
    Route::get('messages', 'admin.Messages/index');
    Route::post('messages', 'admin.Messages/store');

    /** M27 訂閱名單 */
    Route::get('newsletter-subscribers', 'admin.Newsletter/index');
    Route::post('newsletter-subscribers/:id/status', 'admin.Newsletter/status');
    Route::delete('newsletter-subscribers/:id', 'admin.Newsletter/delete');
})->middleware([AdminAuth::class, AdminAclGate::class]);
