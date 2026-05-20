<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Controller, Request, Response};
use App\Domain\Auth\User;
use App\Core\{Validator, Container};
use App\Support\GeocodingService;
use App\Support\ResponseHelper;
use PDO;

class AddressController extends Controller
{
    private User $userModel;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->userModel = new User($container->database());
    }

    /** GET /api/v1/user/addresses - List addresses for current user (default first) */
    public function index(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        if (!$user) {
            return $res->json(ResponseHelper::unauthorized());
        }
        $userId = (int) $user['user_id'];
        $pdo = $this->container->database()->getConnection();
        $sql = "SELECT address_id, user_id, receiver_name, phone, address_line, ward, district, province, is_default, lat, lng 
                FROM addresses WHERE user_id = ? ORDER BY is_default DESC, address_id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $res->json(ResponseHelper::success($list));
    }

    /** POST /api/v1/user/addresses - Create address */
    public function store(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        if (!$user) {
            return $res->json(ResponseHelper::unauthorized());
        }
        $data = $req->json();
        $validator = Validator::make($data, [
            'receiver_name' => 'required',
            'phone' => 'required|phone_vn',
            'address_line' => 'required',
            'ward' => 'required',
            'district' => 'required',
            'province' => 'required',
        ]);
        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }
        $userId = (int) $user['user_id'];
        $isDefault = !empty($data['is_default']);
        $phone = preg_replace('/\D/', '', $data['phone']);
        $addressLine = trim($data['address_line']);
        $ward = trim($data['ward']);
        $district = trim($data['district']);
        $province = trim($data['province']);
        $coordinates = self::resolveCoordinates($data, $addressLine, $ward, $district, $province);
        if (!$coordinates) {
            return $res->json(ResponseHelper::validationError([
                'address_line' => 'Không thể xác định tọa độ cho địa chỉ này. Vui lòng kiểm tra lại thông tin địa chỉ.',
            ]));
        }
        $pdo = $this->container->database()->getConnection();
        if ($isDefault) {
            $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        }
        $sql = "INSERT INTO addresses (user_id, receiver_name, phone, address_line, ward, district, province, is_default, lat, lng) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $userId,
            trim($data['receiver_name']),
            $phone,
            $addressLine,
            $ward,
            $district,
            $province,
            $isDefault ? 1 : 0,
            $coordinates['lat'],
            $coordinates['lng'],
        ]);
        $id = (int) $pdo->lastInsertId();
        return $res->json(ResponseHelper::success(['address_id' => $id], 'Address created'), 201);
    }

    /** PUT /api/v1/user/addresses/{id} - Update address */
    public function update(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        if (!$user) {
            return $res->json(ResponseHelper::unauthorized());
        }
        $id = (int) $req->getAttribute('id');
        if ($id <= 0) {
            return $res->json(ResponseHelper::validationError(['id' => 'Invalid address id']), 422);
        }
        $userId = (int) $user['user_id'];
        $pdo = $this->container->database()->getConnection();
        $stmt = $pdo->prepare("SELECT address_id FROM addresses WHERE address_id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetch()) {
            return $res->json(ResponseHelper::notFound('Address not found'));
        }
        $data = $req->json();
        $validator = Validator::make($data, [
            'receiver_name' => 'required',
            'phone' => 'required|phone_vn',
            'address_line' => 'required',
            'ward' => 'required',
            'district' => 'required',
            'province' => 'required',
        ]);
        if (!$validator->validate()) {
            return $res->json(ResponseHelper::validationError($validator->getErrors()));
        }
        $isDefault = !empty($data['is_default']);
        $phone = preg_replace('/\D/', '', $data['phone']);
        $addressLine = trim($data['address_line']);
        $ward = trim($data['ward']);
        $district = trim($data['district']);
        $province = trim($data['province']);
        $coordinates = self::resolveCoordinates($data, $addressLine, $ward, $district, $province);
        if (!$coordinates) {
            return $res->json(ResponseHelper::validationError([
                'address_line' => 'Không thể xác định tọa độ cho địa chỉ này. Vui lòng kiểm tra lại thông tin địa chỉ.',
            ]));
        }
        if ($isDefault) {
            $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        }
        $sql = "UPDATE addresses SET receiver_name = ?, phone = ?, address_line = ?, ward = ?, district = ?, province = ?, is_default = ?, lat = ?, lng = ? WHERE address_id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            trim($data['receiver_name']),
            $phone,
            $addressLine,
            $ward,
            $district,
            $province,
            $isDefault ? 1 : 0,
            $coordinates['lat'],
            $coordinates['lng'],
            $id,
            $userId,
        ]);
        return $res->json(ResponseHelper::success(null, 'Address updated'));
    }

    /** DELETE /api/v1/user/addresses/{id} */
    public function destroy(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        if (!$user) {
            return $res->json(ResponseHelper::unauthorized());
        }
        $id = (int) $req->getAttribute('id');
        if ($id <= 0) {
            return $res->json(ResponseHelper::validationError(['id' => 'Invalid address id']), 422);
        }
        $userId = (int) $user['user_id'];
        $pdo = $this->container->database()->getConnection();
        $stmt = $pdo->prepare("DELETE FROM addresses WHERE address_id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        if ($stmt->rowCount() === 0) {
            return $res->json(ResponseHelper::notFound('Address not found'));
        }
        return $res->json(ResponseHelper::success(null, 'Address deleted'));
    }

    /** PUT /api/v1/user/addresses/{id}/default - Set as default */
    public function setDefault(Request $req, Response $res)
    {
        $user = $req->getAttribute('user');
        if (!$user) {
            return $res->json(ResponseHelper::unauthorized());
        }
        $id = (int) $req->getAttribute('id');
        if ($id <= 0) {
            return $res->json(ResponseHelper::validationError(['id' => 'Invalid address id']), 422);
        }
        $userId = (int) $user['user_id'];
        $pdo = $this->container->database()->getConnection();
        $stmt = $pdo->prepare("SELECT address_id FROM addresses WHERE address_id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetch()) {
            return $res->json(ResponseHelper::notFound('Address not found'));
        }
        $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("UPDATE addresses SET is_default = 1 WHERE address_id = ? AND user_id = ?")->execute([$id, $userId]);
        return $res->json(ResponseHelper::success(null, 'Default address updated'));
    }

    /**
     * Prefer client lat/lng from Mapbox; fallback to server geocoding.
     *
     * @param array<string, mixed> $data
     * @return array{lat: float, lng: float}|null
     */
    private static function resolveCoordinates(
        array $data,
        string $addressLine,
        string $ward,
        string $district,
        string $province
    ): ?array {
        if (isset($data['lat'], $data['lng']) && $data['lat'] !== '' && $data['lng'] !== '') {
            $lat = (float) $data['lat'];
            $lng = (float) $data['lng'];
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && ($lat !== 0.0 || $lng !== 0.0)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        return GeocodingService::resolveFromParts($addressLine, $ward, $district, $province);
    }
}
