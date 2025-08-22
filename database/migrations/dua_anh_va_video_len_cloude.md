0) Chuẩn bị & cài đặt

Cài SDK Cloudinary PHP

composer require cloudinary/cloudinary_php:^2


(Khuyến nghị) Nạp biến môi trường từ .env
Cài thư viện dotenv (tuỳ chọn, nhưng rất tiện):

composer require vlucas/phpdotenv


Tạo file .env (ở thư mục gốc dự án)

# THÔNG TIN TÀI KHOẢN CỦA BẠN
CLOUD_NAME=dknwpznzc
key Name=cloudinary_3d_366d1e5e-2d97-4387-bff5-6f4838977628
API_KEY=391689363897318
API_SECRET=nreK5dlTIYS_ZEFP2Ug_soh4hM

# Hoặc dùng 1 dòng CLOUDINARY_URL (tuỳ bạn chọn 1 trong 2 cách)
# CLOUDINARY_URL=cloudinary://<API_KEY>:<API_SECRET>@dknwpznzc


Bạn có Cloud name = dknwpznzc và API Key = 391689363897318 (theo ảnh bạn gửi).
API Secret: mở màn hình API Keys → bấm icon con mắt để copy. Không chia sẻ Secret công khai.

1) File dùng lại: CloudinaryService.php

Chỉ cần một file này là đủ. Nó:

Tự đọc config từ .env hoặc biến môi trường.

Cung cấp hàm upload ảnh, upload video, upload hàng loạt, tạo URL biến thể, xoá file.

Dễ tái sử dụng ở controller/service khác.

<?php
/**
 * CloudinaryService.php
 * Service gọn nhẹ để upload ảnh & video lên Cloudinary (PHP SDK v2)
 * Tác giả: <bạn điền tên nhóm/công ty>
 */

declare(strict_types=1);

namespace App\Services;

use Cloudinary\Configuration\Configuration;
use Cloudinary\Uploader;
use Cloudinary\Cloudinary;

class CloudinaryService
{
    private Cloudinary $cld;

    /**
     * Khởi tạo:
     * - Ưu tiên đọc từ biến môi trường CLOUDINARY_URL
     * - Nếu không có, sẽ đọc CLOUD_NAME, API_KEY, API_SECRET
     * - Có thể override qua $options
     *
     * $options = [
     *   'cloud_name' => '...',
     *   'api_key'    => '...',
     *   'api_secret' => '...',
     *   'secure'     => true
     * ]
     */
    public function __construct(array $options = [])
    {
        // Nếu có dotenv, tự động load .env
        if (class_exists(\Dotenv\Dotenv::class)) {
            $root = $_ENV['PROJECT_ROOT'] ?? dirname(__DIR__, 2); // đoán thư mục gốc
            if (file_exists($root.'/.env')) {
                $dotenv = \Dotenv\Dotenv::createImmutable($root);
                $dotenv->safeLoad();
            }
        }

        // Cho phép cấu hình qua CLOUDINARY_URL
        $cloudinaryUrl = $options['cloudinary_url'] ?? getenv('CLOUDINARY_URL');

        if ($cloudinaryUrl) {
            Configuration::instance([
                'cloud' => ['cloudinary_url' => $cloudinaryUrl],
                'url'   => ['secure' => true],
            ]);
        } else {
            $cloudName = $options['cloud_name'] ?? getenv('CLOUD_NAME');
            $apiKey    = $options['api_key']    ?? getenv('API_KEY');
            $apiSecret = $options['api_secret'] ?? getenv('API_SECRET');

            if (!$cloudName || !$apiKey || !$apiSecret) {
                throw new \RuntimeException(
                    'Thiếu cấu hình Cloudinary. Hãy set CLOUDINARY_URL hoặc (CLOUD_NAME, API_KEY, API_SECRET).'
                );
            }

            Configuration::instance([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key'    => $apiKey,
                    'api_secret' => $apiSecret,
                ],
                'url' => ['secure' => (bool)($options['secure'] ?? true)],
            ]);
        }

        $this->cld = new Cloudinary();
    }

    /**
     * Upload 1 ảnh từ đường dẫn local.
     * @param string $path      Đường dẫn file local
     * @param array  $options   folder, public_id, overwrite, tags, context,...
     * @return array            Kết quả Cloudinary trả về (chứa secure_url, public_id,...)
     */
    public function uploadImage(string $path, array $options = []): array
    {
        $defaults = [
            'resource_type'   => 'image',
            'folder'          => 'products',
            'overwrite'       => true,
            'use_filename'    => true,
            'unique_filename' => false,
        ];
        return Uploader::upload($path, array_merge($defaults, $options));
    }

    /**
     * Upload 1 video từ đường dẫn local.
     * @param string $path
     * @param array  $options   folder, public_id, overwrite, eager (tạo thumbnail/preview)...
     * @return array
     */
    public function uploadVideo(string $path, array $options = []): array
    {
        $defaults = [
            'resource_type'   => 'video',
            'folder'          => 'videos',
            'overwrite'       => true,
            'use_filename'    => true,
            'unique_filename' => false,
        ];
        return Uploader::upload($path, array_merge($defaults, $options));
    }

    /**
     * Upload hàng loạt ảnh trong 1 thư mục (jpg/png/webp mặc định).
     * @param string        $dir
     * @param string        $pattern glob
     * @param callable|null $perFileOptionsFn  function($path, $index): array  // trả options riêng cho từng file
     * @return array[]      Danh sách kết quả từng file
     */
    public function uploadImagesInDir(string $dir, string $pattern = '*.{jpg,jpeg,png,webp}', ?callable $perFileOptionsFn = null): array
    {
        $results = [];
        foreach (glob(rtrim($dir, '/').'/'.$pattern, GLOB_BRACE) as $i => $path) {
            $opts = $perFileOptionsFn ? $perFileOptionsFn($path, $i) : [];
            $results[] = $this->uploadImage($path, $opts);
        }
        return $results;
    }

    /**
     * Tạo URL xem ảnh với biến thể (resize/crop/format/quality) mà KHÔNG cần re-upload.
     * @param string $publicId
     * @param array  $transforms Ví dụ:
     *      [
     *        'width' => 600, 'height' => 600, 'crop' => 'fill', 'gravity' => 'auto',
     *        'format' => 'webp', 'quality' => 'auto'
     *      ]
     */
    public function imageUrl(string $publicId, array $transforms = []): string
    {
        $img = $this->cld->image($publicId);

        if (isset($transforms['width'], $transforms['height'], $transforms['crop'])) {
            $img = $img->resize(
                \Cloudinary\Transformation\Resize::{$transforms['crop']}(
                    (int)$transforms['width'], (int)$transforms['height']
                )
            );
        }

        if (!empty($transforms['gravity'])) {
            $img = $img->gravity(\Cloudinary\Transformation\Gravity::fromValue($transforms['gravity']));
        }
        if (!empty($transforms['format'])) {
            $img = $img->format($transforms['format']);
        }
        if (!empty($transforms['quality'])) {
            $img = $img->quality($transforms['quality']);
        }
        return $img->toUrl();
    }

    /**
     * Xoá file theo public_id.
     * @param string $publicId
     * @param string $resourceType 'image' | 'video' | 'raw'
     */
    public function delete(string $publicId, string $resourceType = 'image'): array
    {
        return Uploader::destroy($publicId, ['resource_type' => $resourceType]);
    }

    /**
     * Helpers gợi ý đặt public_id theo SKU/user để dễ quản lý.
     */
    public static function makePublicId(string $prefix, string $slug, ?int $index = null): string
    {
        $base = trim($prefix, '/').'/'.trim($slug, '/');
        return $index === null ? $base : $base.'-'.$index;
    }
}

2) Ví dụ dùng nhanh
a) upload_one.php — upload 1 ảnh & 1 video
<?php
require __DIR__.'/vendor/autoload.php';

use App\Services\CloudinaryService;

$cloud = new CloudinaryService(); // đọc từ .env

// ẢNH
$img = $cloud->uploadImage(__DIR__.'/storage/products/sku-123/main.jpg', [
    'folder'     => 'shop/products/sku-123',
    'public_id'  => 'sku-123-main',
    'tags'       => ['user:42','product:SKU-123'],
    'context'    => ['user_id' => '42', 'product_id' => '123'],
]);
echo "Image URL: {$img['secure_url']}\n";
echo "Image public_id: {$img['public_id']}\n";

// VIDEO
$vid = $cloud->uploadVideo(__DIR__.'/storage/videos/intro.mp4', [
    'folder'     => 'shop/videos',
    'public_id'  => 'intro-001',
    // 'eager' => [['format' => 'mp4', 'width' => 720, 'height' => 720, 'crop' => 'pad']],
]);
echo "Video URL: {$vid['secure_url']}\n";
echo "Video public_id: {$vid['public_id']}\n";

// Tạo URL thumbnail 400x400 webp
$thumb = $cloud->imageUrl($img['public_id'], [
    'width' => 400, 'height' => 400, 'crop' => 'fill',
    'gravity' => 'auto', 'format' => 'webp', 'quality' => 'auto'
]);
echo "Thumb URL: {$thumb}\n";

b) upload_dir.php — upload hàng loạt ảnh trong thư mục
<?php
require __DIR__.'/vendor/autoload.php';

use App\Services\CloudinaryService;

$cloud = new CloudinaryService();

$sku = 'SKU-999';
$dir = __DIR__.'/storage/products/'.$sku;

$results = $cloud->uploadImagesInDir($dir, '*.{jpg,jpeg,png,webp}', function($path, $idx) use ($sku) {
    return [
        'folder'     => "shop/products/{$sku}",
        'public_id'  => "{$sku}-".($idx+1),
        'tags'       => ["product:{$sku}"],
        'overwrite'  => true,
    ];
});

foreach ($results as $r) {
    echo $r['public_id']." => ".$r['secure_url']."\n";
}

3) Tích hợp vào MVC/Controller

Đặt CloudinaryService.php vào app/Services/.

Ở Controller: khởi tạo new CloudinaryService() một lần (hoặc inject).

Khi tạo/sửa sản phẩm:

Gọi $cloud->uploadImage() với folder theo chuẩn tenants/{user_id}/products/{sku}.

Lưu secure_url + public_id vào bảng product_images.

Khi hiển thị ảnh: dùng secure_url hoặc tạo biến thể qua $cloud->imageUrl($publicId, [...]).

Khi xóa ảnh: $cloud->delete($publicId, 'image').

4) Kiểm tra đúng “cloud của bạn”

URL Cloudinary luôn có dạng:

https://res.cloudinary.com/<cloud_name>/<resource_type>/upload/v<version>/<path>/<public_id>.<ext>


Với tài khoản của bạn, <cloud_name> = dknwpznzc.

Chỉ cần thấy .../res.cloudinary.com/dknwpznzc/... là chắc chắn file nằm trong đúng cloud của bạn.

5) Best practices (rất quan trọng)

Không commit API Secret vào Git. Luôn dùng .env hoặc biến môi trường.

Lưu public_id cùng với URL trong DB để xoá/sinh URL biến thể dễ dàng.

Quy ước folder/tags/context để biết “ảnh của user nào/sản phẩm nào” (ví dụ: tenants/{user_id}/products/{sku}).

Khi cập nhật ảnh, để overwrite: true — Cloudinary sẽ tăng version trong URL → tránh cache cũ.

Video: cân nhắc eager transformations (tạo trước preview/thumbnail) nếu cần phát nhanh.

Có thể tạo Upload Preset nếu upload trực tiếp từ frontend (unsigned). Backend (cách ở đây) là an toàn nhất.