# 別システム連携API 仕様書（Conservation API）

物品（stocks）・在庫格納先（stock_storages）・ユーザー（users）を扱う REST API の仕様です。  
実行元システムからの連携実装時に本ドキュメントを参照してください。

---

## 1. 基本事項

### 1.1 ベースURL

```
https://akioka.cloud/api
```

例: `https://example.com/api` の場合、物品一覧は `GET https://example.com/api/stocks` で取得します。

### 1.2 認証

**現時点では認証不要**です。必要に応じて実行環境で API Key やトークンによる認証を追加してください。

### 1.3 リクエスト形式

- **Content-Type**: `application/json` を推奨（POST / PUT / PATCH 時）
- **文字コード**: UTF-8

### 1.4 レスポンス形式

- すべて **JSON**
- 成功時: ステータスコード 200 / 201 / 207 とともにデータを返却
- エラー時: 4xx / 5xx とともに `message` または Laravel 標準のバリデーションエラー形式

---

## 2. 物品（Stocks）API

物品マスタの取得・登録・更新・論理削除ができます。  
レスポンスには在庫格納先（storage_address / location）、取引先（supplier）、別名（aliases）、画像（stock_images）が含まれます。

### 2.1 物品一覧・検索

**有効（del_flg=0）の物品のみ取得**されます。検索方法は次のいずれかです。

- **name / s_name** … 品名・品番の部分一致
- **ids** … 複数の物品IDを指定して取得（カンマ区切り、または配列）

`ids` を指定した場合は、指定したIDのうち有効なものだけが返却されます。`ids` と name / s_name を同時に指定した場合は、**ids で絞り込んだうえで** name / s_name でさらに絞り込みます。

| 項目 | 内容 |
|------|------|
| **メソッド** | GET |
| **URL** | `/api/stocks` |
| **クエリパラメータ** | すべて任意 |

#### クエリパラメータ一覧

| パラメータ | 型 | 説明 |
|------------|-----|------|
| ids | string または array | 物品IDのリスト。カンマ区切り（例: `1,2,3`）または `ids[]=1&ids[]=2` |
| name | string | 品名（部分一致） |
| s_name | string | 品番（部分一致） |
| per_page | integer | 1件あたりの件数（1〜100、省略時 15） |

#### リクエスト例

```
# name / s_name で検索
GET /api/stocks?name=テスト&per_page=20
GET /api/stocks?s_name=SPL
GET /api/stocks?name=品&s_name=001

# 複数IDで取得
GET /api/stocks?ids=1,2,3
GET /api/stocks?ids=1,2,3&per_page=10
```

#### レスポンス例（200 OK）

ページネーション形式（Laravel 標準）です。

```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "name": "サンプル品名",
      "s_name": "SPL-001",
      "stock_no": "STK001",
      "jan_code": "4901234567890",
      "price": "1000.00",
      "del_flg": 0,
      "stock_storages": [
        {
          "id": 10,
          "stock_id": 1,
          "storage_address_id": 2,
          "quantity": 50,
          "storage_address": {
            "id": 2,
            "address": "A-1-2",
            "location_id": 1,
            "location": {
              "id": 1,
              "name": "1号倉庫"
            }
          }
        }
      ],
      "stock_suppliers": [
        {
          "id": 5,
          "stock_id": 1,
          "supplier_id": 3,
          "lead_time": 7,
          "main_flg": 1,
          "supplier": {
            "id": 3,
            "supplier_no": "SUP001",
            "name": "○○商事",
            "tel": "03-1234-5678",
            "address": "東京都..."
          }
        }
      ],
      "aliases": [
        { "id": 1, "stock_id": 1, "alias": "別名1" }
      ],
      "stock_images": []
    }
  ],
  "first_page_url": "...",
  "from": 1,
  "last_page": 5,
  "last_page_url": "...",
  "links": [...],
  "path": "...",
  "per_page": 15,
  "to": 15,
  "total": 72
}
```

#### 使い方のポイント（実行元実装）

- 一覧取得後は `data` 配列をループして利用します。
- **複数IDで取得する場合**: `ids=1,2,3` のようにカンマ区切りで指定すると、該当する有効な物品だけが `data` に含まれます。存在しないIDや削除済み（del_flg=1）のIDは結果に含まれません。
- 在庫数は `stock_storages[].quantity` の合計、または格納先ごとに `stock_storages[].storage_address` / `location` で場所を判別できます。
- 取引先は `stock_suppliers[].supplier` で取得できます。`main_flg` が 1 のものが主取引先の目安です。

---

### 2.2 物品1件取得

| 項目 | 内容 |
|------|------|
| **メソッド** | GET |
| **URL** | `/api/stocks/{id}` |
| **パス** | id … 物品ID（整数） |

#### リクエスト例

```
GET /api/stocks/1
```

#### レスポンス例（200 OK）

2.1 の `data` の1要素と同じ構造のオブジェクトがそのまま返ります（ページネーションはありません）。

```json
{
  "id": 1,
  "name": "サンプル品名",
  "s_name": "SPL-001",
  "stock_storages": [...],
  "stock_suppliers": [...],
  "aliases": [...],
  "stock_images": [...]
}
```

#### エラー

- 存在しない ID: **404 Not Found**

---

### 2.3 物品新規登録

| 項目 | 内容 |
|------|------|
| **メソッド** | POST |
| **URL** | `/api/stocks` |
| **Body** | JSON（下記） |

#### リクエスト Body（JSON）

| キー | 型 | 必須 | 説明 |
|------|-----|------|------|
| name | string | **必須** | 品名（最大255文字） |
| s_name | string | - | 品番 |
| stock_no | string | - | 品番（stock_no） |
| img_path | string | - | 画像パス |
| url | string | - | URL |
| stock_process_id | string | - | 工程ID |
| tax_included | boolean | - | 税込フラグ |
| price | number | - | 単価 |
| solo_unit | string | - | 単位（単体） |
| org_unit | string | - | 単位（原単位） |
| quantity_per_org | integer | - | 原単位あたり数量（0以上） |
| deli_location | string | - | 納品先・納品場所 |
| memo | string | - | メモ |
| classification_id | integer | - | 分類ID |
| not_stock_flg | boolean | - | 在庫管理対象外フラグ |
| purchase_identification_number | string | - | 購入識別番号 |
| jan_code | string | - | JANコード |
| main_unit_flg | integer | - | 主単位フラグ |
| price_check_flg | integer | - | 価格チェックフラグ |
| approval_supplier_name | string | - | 承認取引先名 |
| special_area_cd | integer | - | 特別エリアコード |
| desc_memo | string | - | 説明メモ（最大255） |
| show_price_on_invoice | integer | - | 請求書価格表示フラグ |

登録時は **del_flg は常に 0** で保存されます（指定不要）。

#### リクエスト例

```json
POST /api/stocks
Content-Type: application/json

{
  "name": "新規品名",
  "s_name": "NEW-001",
  "jan_code": "4900000000001",
  "price": 500
}
```

#### レスポンス例（201 Created）

登録された物品オブジェクト（2.2 と同構造）が返ります。在庫格納先・取引先・別名・画像は空配列のことが多いです。

#### エラー

- バリデーションエラー（例: name 未指定）: **422 Unprocessable Entity** + エラー詳細

---

### 2.4 物品更新

| 項目 | 内容 |
|------|------|
| **メソッド** | PUT または PATCH |
| **URL** | `/api/stocks/{id}` |
| **Body** | JSON（更新したい項目のみで可） |

#### リクエスト Body（JSON）

2.3 と同じ項目が使えます。ただし **name は省略可能**（`sometimes`）で、送った項目だけが更新されます。  
**del_flg** は 0 または 1 を指定可能です。

#### リクエスト例

```json
PUT /api/stocks/1
Content-Type: application/json

{
  "name": "品名変更後",
  "price": 1200
}
```

#### レスポンス例（200 OK）

更新後の物品オブジェクト（2.2 と同構造）が返ります。

#### エラー

- 存在しない ID: **404 Not Found**
- バリデーションエラー: **422 Unprocessable Entity**

---

### 2.5 物品論理削除

| 項目 | 内容 |
|------|------|
| **メソッド** | DELETE |
| **URL** | `/api/stocks/{id}` |

物理削除ではなく、**del_flg を 1 に更新**します。一覧・検索で `del_flg=0` を指定している限り、削除済みは取得されません。

#### レスポンス例（200 OK）

```json
{
  "message": "Deleted"
}
```

#### エラー

- 存在しない ID: **404 Not Found**

---

## 3. 在庫格納先（Stock Storages）API

在庫格納先（`stock_storages`）の「数量」を操作する API です。  
格納先は「物品 × 保管場所（storage_address / location）」ごとに1レコードあります。

### 3.1 在庫数量の減算

指定した格納先の現在数量から、指定数量を引きます。在庫不足の場合はエラーにせず、結果配列で `success: false` を返します。

| 項目 | 内容 |
|------|------|
| **メソッド** | POST |
| **URL** | `/api/stock-storages/subtract` |
| **Body** | JSON（単体オブジェクト または 配列） |

#### リクエスト Body（JSON）

**単体で1件だけ減算する場合**

```json
{
  "stock_storage_id": 10,
  "quantity": 5
}
```

- **stock_storage_id**（必須）: 在庫格納先の ID（`stock_storages.id`）
- **quantity**（必須）: 減算する数量（1以上の整数）

**複数件を一度に減算する場合**

```json
[
  { "stock_storage_id": 10, "quantity": 5 },
  { "stock_storage_id": 11, "quantity": 3 }
]
```

配列の要素は上記と同じ形式です。先頭から順に処理されます。

#### リクエスト例

```bash
# 単体
curl -X POST "https://example.com/api/stock-storages/subtract" \
  -H "Content-Type: application/json" \
  -d '{"stock_storage_id": 10, "quantity": 5}'

# 複数
curl -X POST "https://example.com/api/stock-storages/subtract" \
  -H "Content-Type: application/json" \
  -d '[{"stock_storage_id": 10, "quantity": 5}, {"stock_storage_id": 11, "quantity": 3}]'
```

#### レスポンス例（200 OK・全件成功）

```json
{
  "results": [
    {
      "index": 0,
      "success": true,
      "stock_storage_id": 10,
      "previous_quantity": 100,
      "subtracted": 5,
      "new_quantity": 95
    }
  ]
}
```

#### レスポンス例（207 Multi-Status・一部失敗）

在庫不足やバリデーションエラーがある場合、該当要素だけ `success: false` となり、HTTP ステータスは **207** になります。

```json
{
  "results": [
    {
      "index": 0,
      "success": true,
      "stock_storage_id": 10,
      "previous_quantity": 100,
      "subtracted": 5,
      "new_quantity": 95
    },
    {
      "index": 1,
      "success": false,
      "error": "Insufficient quantity.",
      "stock_storage_id": 11,
      "current_quantity": 2,
      "requested_subtract": 5
    }
  ]
}
```

#### エラー（レスポンス内）

- **success: false** の要素: `error` に理由（例: "Insufficient quantity.", バリデーション文言）が入ります。
- Body が配列でもオブジェクトでもない場合: **422** で `{"message": "Invalid request body"}` を返します。

#### 使い方のポイント（実行元実装）

1. 物品一覧／1件取得で取得した `stock_storages[].id` を `stock_storage_id` に指定します。
2. 単体のときはオブジェクト1つ、複数件のときは配列で送ります。
3. レスポンスの `results` をループし、`success` が true のものは `new_quantity` で反映済み、false のものは `error` を表示するか再処理します。
4. 同時実行を防ぐため、サーバ側で行ロック（lockForUpdate）を行っています。

---

### 3.2 在庫数量の上書き（棚卸）

棚卸で実地数量を反映するための API です。指定した格納先の数量を、指定した数値で**上書き**します。

| 項目 | 内容 |
|------|------|
| **メソッド** | PUT |
| **URL** | `/api/stock-storages/{id}` |
| **パス** | id … 在庫格納先の ID（`stock_storages.id`） |
| **Body** | JSON |

#### リクエスト Body（JSON）

```json
{
  "quantity": 50
}
```

- **quantity**（必須）: 上書きする数量（0以上の整数）

#### リクエスト例

```bash
curl -X PUT "https://example.com/api/stock-storages/10" \
  -H "Content-Type: application/json" \
  -d '{"quantity": 50}'
```

#### レスポンス例（200 OK）

```json
{
  "stock_storage_id": 10,
  "quantity": 50
}
```

#### エラー

- 存在しない ID: **404 Not Found**
- quantity 未指定や 0 未満: **422 Unprocessable Entity**（バリデーションエラー）

#### 使い方のポイント（実行元実装）

1. 物品取得で得た `stock_storages[].id` を URL の `{id}` に指定します。
2. 実地棚卸で数えた数量を `quantity` にセットして PUT すると、DB の数量がその値に更新されます。
3. 複数格納先を棚卸する場合は、格納先ごとに上記 PUT を1回ずつ呼び出してください。

---

## 4. ユーザー（Users）API

ユーザー（利用者・担当者）の取得のみです。レスポンスには group（所属）・position（役職）・process（工程）が含まれます。  
**password / remember_token は返却しません。**

### 4.1 ユーザー一覧・検索

**有効（del_flg=0）のユーザーのみ取得**されます。検索条件は **name（氏名）の部分一致のみ**です。

| 項目 | 内容 |
|------|------|
| **メソッド** | GET |
| **URL** | `/api/users` |
| **クエリパラメータ** | すべて任意 |

#### クエリパラメータ一覧

| パラメータ | 型 | 説明 |
|------------|-----|------|
| name | string | 氏名（部分一致） |
| per_page | integer | 1件あたりの件数（1〜100、省略時 15） |

#### リクエスト例

```
GET /api/users?name=山田
GET /api/users?name=太郎&per_page=20
```

#### レスポンス例（200 OK）

物品一覧と同様のページネーション形式です。

```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "emp_no": "000001",
      "name": "山田太郎",
      "email": "yamada@example.com",
      "group_id": 1,
      "position_id": 2,
      "process_id": 3,
      "group": {
        "id": 1,
        "name": "総務部",
        "phone_number": null
      },
      "position": {
        "id": 2,
        "name": "主任"
      },
      "process": {
        "id": 3,
        "name": "製造工程A",
        "place_id": null,
        "division": 1
      }
    }
  ],
  "per_page": 15,
  "total": 100,
  ...
}
```

#### 使い方のポイント（実行元実装）

- 担当者リストや所属・工程で絞り込む場合に利用します。
- `group` / `position` / `process` で名称を表示する際は、これらのオブジェクトを参照してください。

---

### 4.2 ユーザー1件取得

| 項目 | 内容 |
|------|------|
| **メソッド** | GET |
| **URL** | `/api/users/{id}` |
| **パス** | id … ユーザーID（整数） |

#### レスポンス例（200 OK）

一覧の `data` の1要素と同じ構造のオブジェクトが返ります（group / position / process 含む）。password / remember_token は含まれません。

#### エラー

- 存在しない ID: **404 Not Found**

---

## 5. エラー・ステータスコード一覧

| ステータス | 意味 |
|------------|------|
| 200 | 成功（取得・更新・削除など） |
| 201 | 作成成功（POST で新規登録） |
| 207 | 複数件処理の一部失敗（減算 API で在庫不足など） |
| 404 | リソースが存在しない（id 不一致） |
| 422 | バリデーションエラー（必須不足・型・範囲など） |
| 500 | サーバエラー |

422 のときは、Laravel のバリデーション形式で `message` や `errors` が返ることがあります。実行元では `errors` のキーごとのメッセージを表示すると利用者に分かりやすくなります。

---

## 6. 実行元での実装の流れ（推奨）

1. **ベースURLの設定**  
   環境ごとに `https://（ドメイン）/api` を設定し、各エンドポイントは相対パス（例: `/stocks`, `/users`）で結合する。

2. **物品の利用**  
   - 一覧: `GET /api/stocks` で name / s_name（部分一致）または per_page を指定し、`data` を利用。  
   - 複数ID取得: `GET /api/stocks?ids=1,2,3` で指定したIDの物品をまとめて取得（有効なもののみ）。  
   - 1件: `GET /api/stocks/{id}` で在庫格納先・取引先・別名をまとめて取得。  
   - 在庫減算: 出庫時に `POST /api/stock-storages/subtract` で `stock_storage_id` と数量を送る。  
   - 棚卸: 実地数量を `PUT /api/stock-storages/{id}` の `quantity` で上書き。

3. **ユーザーの利用**  
   - 担当者一覧: `GET /api/users` で name（氏名・部分一致）などを指定。  
   - 1件: `GET /api/users/{id}` で所属・役職・工程名を取得。

4. **エラー処理**  
   - 4xx / 5xx のときはステータスコードと body をログまたは画面に表示。  
   - 減算 API の 207 のときは `results` の `success: false` をチェックし、在庫不足等を案内する。

5. **Content-Type**  
   POST / PUT / PATCH では `Content-Type: application/json` を付与し、Body は JSON 文字列で送ること。

---

以上が、別システム連携用 物品・ユーザー API の仕様です。実装時に不明点があれば、本システムの担当者に問い合わせてください。
