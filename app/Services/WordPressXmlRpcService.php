<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WordPressXmlRpcService
{
    /**
     * XML-RPC エンドポイントを取得
     * 
     * @param Shop $shop
     * @return string
     */
    private function getXmlRpcEndpoint(Shop $shop): string
    {
        $base = rtrim($shop->wp_base_url, '/');
        return $base . '/xmlrpc.php';
    }

    /**
     * XML-RPC リクエストを送信
     * 
     * @param string $endpoint
     * @param string $method
     * @param array $params
     * @return array|null
     */
    private function sendXmlRpcRequest(string $endpoint, string $method, array $params): ?array
    {
        try {
            // XML-RPC リクエストボディを構築
            $xml = $this->buildXmlRpcRequest($method, $params);

            Log::info('WP_XMLRPC_REQUEST', [
                'endpoint' => $endpoint,
                'method' => $method,
                'xml_length' => strlen($xml),
            ]);

            // XML-RPC リクエストを送信
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml',
            ])
                ->timeout(30)
                ->withBody($xml, 'text/xml')
                ->post($endpoint);

            if (!$response->successful()) {
                Log::error('WP_XMLRPC_HTTP_ERROR', [
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);
                return null;
            }

            // XML-RPC レスポンスをパース
            return $this->parseXmlRpcResponse($response->body());
        } catch (\Exception $e) {
            Log::error('WP_XMLRPC_EXCEPTION', [
                'endpoint' => $endpoint,
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * XML-RPC リクエストボディを構築
     * 
     * @param string $method
     * @param array $params
     * @return string
     */
    private function buildXmlRpcRequest(string $method, array $params): string
    {
        $xml = '<?xml version="1.0"?>' . "\n";
        $xml .= '<methodCall>' . "\n";
        $xml .= '  <methodName>' . htmlspecialchars($method, ENT_XML1) . '</methodName>' . "\n";
        $xml .= '  <params>' . "\n";

        foreach ($params as $param) {
            $xml .= '    <param>' . "\n";
            $xml .= '      <value>' . $this->buildXmlRpcValue($param) . '</value>' . "\n";
            $xml .= '    </param>' . "\n";
        }

        $xml .= '  </params>' . "\n";
        $xml .= '</methodCall>';

        return $xml;
    }

    /**
     * XML-RPC 値を構築（再帰的）
     * 
     * @param mixed $value
     * @return string
     */
    private function buildXmlRpcValue($value): string
    {
        if (is_int($value)) {
            return '<int>' . $value . '</int>';
        } elseif (is_bool($value)) {
            return '<boolean>' . ($value ? '1' : '0') . '</boolean>';
        } elseif (is_array($value)) {
            // 連想配列の場合は struct、数値配列の場合は array
            if (array_keys($value) !== range(0, count($value) - 1)) {
                // 連想配列（struct）
                $xml = '<struct>' . "\n";
                foreach ($value as $key => $val) {
                    $xml .= '        <member>' . "\n";
                    $xml .= '          <name>' . htmlspecialchars($key, ENT_XML1) . '</name>' . "\n";
                    $xml .= '          <value>' . $this->buildXmlRpcValue($val) . '</value>' . "\n";
                    $xml .= '        </member>' . "\n";
                }
                $xml .= '      </struct>';
                return $xml;
            } else {
                // 数値配列（array）
                $xml = '<array>' . "\n";
                $xml .= '        <data>' . "\n";
                foreach ($value as $val) {
                    $xml .= '          <value>' . $this->buildXmlRpcValue($val) . '</value>' . "\n";
                }
                $xml .= '        </data>' . "\n";
                $xml .= '      </array>';
                return $xml;
            }
        } else {
            // 文字列
            return '<string>' . htmlspecialchars((string)$value, ENT_XML1) . '</string>';
        }
    }

    /**
     * XML-RPC レスポンスをパース
     * 
     * @param string $xml
     * @return array|null
     */
    private function parseXmlRpcResponse(string $xml): ?array
    {
        try {
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadXML($xml);

            // fault のチェック
            $fault = $dom->getElementsByTagName('fault')->item(0);
            if ($fault) {
                $faultValue = $fault->getElementsByTagName('value')->item(0);
                if ($faultValue) {
                    $faultStruct = $faultValue->getElementsByTagName('struct')->item(0);
                    if ($faultStruct) {
                        $faultCode = null;
                        $faultString = null;

                        $members = $faultStruct->getElementsByTagName('member');
                        foreach ($members as $member) {
                            $name = $member->getElementsByTagName('name')->item(0)->nodeValue;
                            $value = $member->getElementsByTagName('value')->item(0);
                            $valueText = $value->getElementsByTagName('*')->item(0)->nodeValue ?? '';

                            if ($name === 'faultCode') {
                                $faultCode = (int)$valueText;
                            } elseif ($name === 'faultString') {
                                $faultString = $valueText;
                            }
                        }

                        Log::error('WP_XMLRPC_FAULT', [
                            'faultCode' => $faultCode,
                            'faultString' => $faultString,
                        ]);

                        return [
                            'fault' => true,
                            'faultCode' => $faultCode,
                            'faultString' => $faultString,
                        ];
                    }
                }
            }

            // params の取得
            $params = $dom->getElementsByTagName('param');
            if ($params->length > 0) {
                $param = $params->item(0);
                $value = $param->getElementsByTagName('value')->item(0);
                $result = $this->parseXmlRpcValue($value);

                return [
                    'fault' => false,
                    'result' => $result,
                ];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('WP_XMLRPC_PARSE_ERROR', [
                'error' => $e->getMessage(),
                'xml' => substr($xml, 0, 500),
            ]);
            return null;
        }
    }

    /**
     * XML-RPC 値をパース（再帰的）
     * 
     * @param \DOMElement $element
     * @return mixed
     */
    private function parseXmlRpcValue(\DOMElement $element)
    {
        $child = $element->firstChild;
        while ($child && $child->nodeType !== XML_ELEMENT_NODE) {
            $child = $child->nextSibling;
        }

        if (!$child) {
            return null;
        }

        $tagName = $child->tagName;

        switch ($tagName) {
            case 'int':
            case 'i4':
                return (int)$child->nodeValue;
            case 'boolean':
                return (bool)$child->nodeValue;
            case 'string':
                return $child->nodeValue;
            case 'array':
                $data = $child->getElementsByTagName('data')->item(0);
                $values = $data->getElementsByTagName('value');
                $result = [];
                foreach ($values as $value) {
                    $result[] = $this->parseXmlRpcValue($value);
                }
                return $result;
            case 'struct':
                $members = $child->getElementsByTagName('member');
                $result = [];
                foreach ($members as $member) {
                    $name = $member->getElementsByTagName('name')->item(0)->nodeValue;
                    $value = $member->getElementsByTagName('value')->item(0);
                    $result[$name] = $this->parseXmlRpcValue($value);
                }
                return $result;
            default:
                return $child->nodeValue;
        }
    }

    /**
     * WordPress REST APIで投稿を作成（XML-RPC版）
     * 
     * @param Shop $shop 店舗オブジェクト
     * @param array $payload 投稿データ
     * @return array|null 投稿成功時はレスポンス配列、失敗時はnull
     */
    public function createPost(Shop $shop, array $payload): ?array
    {
        try {
            // WordPressサイトのURLを取得
            $wpUrl = $shop->wp_base_url;
            if (!$wpUrl) {
                Log::error('WP_XMLRPC_URL_MISSING', [
                    'shop_id' => $shop->id,
                ]);
                return null;
            }

            // Application Password認証
            $username = $shop->wp_username;
            $appPassword = $shop->wp_app_password;
            
            if (!$username || !$appPassword) {
                Log::error('WP_XMLRPC_CREDENTIALS_MISSING', [
                    'shop_id' => $shop->id,
                    'has_username' => !empty($username),
                    'has_app_password' => !empty($appPassword),
                ]);
                return null;
            }

            // Application Passwordから空白を除去
            $appPassword = str_replace(' ', '', $appPassword);

            // 投稿タイプを取得（デフォルト: post）
            $postType = trim($shop->wp_post_type ?? '');
            if (empty($postType)) {
                $postType = 'post';
            }
            
            // 投稿ステータスを取得（デフォルト: publish）
            $postStatus = $shop->wp_post_status ?? 'publish';

            // XML-RPC エンドポイント
            $endpoint = $this->getXmlRpcEndpoint($shop);

            Log::info('WP_XMLRPC_POST_REQUEST', [
                'shop_id' => $shop->id,
                'endpoint' => $endpoint,
                'username' => $username,
                'post_type' => $postType,
                'post_status' => $postStatus,
                'title_length' => mb_strlen($payload['title'] ?? ''),
                'content_length' => mb_strlen($payload['content'] ?? ''),
            ]);

            // metaWeblog.newPost のパラメータを構築
            // パラメータ: blogid, username, password, struct, publish
            $blogId = 1; // WordPress では通常 1
            $struct = [
                'title' => $payload['title'] ?? '',
                'description' => $payload['content'] ?? '',
                'post_status' => $postStatus,
            ];

            // カテゴリを追加（XML-RPCではカテゴリ名の配列）
            if (!empty($payload['categories']) && is_array($payload['categories'])) {
                // カテゴリIDが渡された場合は名前として扱う（XML-RPCでは名前で指定）
                $struct['categories'] = $payload['categories'];
            }

            // カスタム投稿タイプを指定
            // metaWeblog.newPost では post_type を直接サポートしていないため、
            // wp.newPost を使用するか、カスタムフィールドとして追加
            // ここでは wp.newPost を使用する方法に変更
            if ($postType !== 'post') {
                // wp.newPost を使用（カスタム投稿タイプ対応）
                return $this->createPostWithWpNewPost($shop, $username, $appPassword, $endpoint, $postType, $postStatus, $payload);
            }

            // metaWeblog.newPost を呼び出し
            $result = $this->sendXmlRpcRequest(
                $endpoint,
                'metaWeblog.newPost',
                [
                    $blogId,
                    $username,
                    $appPassword,
                    $struct,
                    true, // publish
                ]
            );

            if (!$result || ($result['fault'] ?? false)) {
                $faultCode = $result['faultCode'] ?? null;
                $faultString = $result['faultString'] ?? 'Unknown error';
                
                Log::error('WP_XMLRPC_POST_FAILED', [
                    'shop_id' => $shop->id,
                    'faultCode' => $faultCode,
                    'faultString' => $faultString,
                    'endpoint' => $endpoint,
                ]);
                return null;
            }

            $postId = $result['result'] ?? null;

            Log::info('WP_XMLRPC_POST_SUCCESS', [
                'shop_id' => $shop->id,
                'wp_post_id' => $postId,
            ]);

            return [
                'id' => $postId,
            ];
        } catch (\Exception $e) {
            Log::error('WP_XMLRPC_POST_EXCEPTION', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * wp.newPost を使用してカスタム投稿タイプで投稿
     * 
     * @param Shop $shop
     * @param string $username
     * @param string $appPassword
     * @param string $endpoint
     * @param string $postType
     * @param string $postStatus
     * @param array $payload
     * @return array|null
     */
    private function createPostWithWpNewPost(
        Shop $shop,
        string $username,
        string $appPassword,
        string $endpoint,
        string $postType,
        string $postStatus,
        array $payload
    ): ?array {
        try {
            // wp.newPost のパラメータを構築
            // パラメータ: blog_id, username, password, content, publish
            $blogId = 1;
            $content = [
                'post_title' => $payload['title'] ?? '',
                'post_content' => $payload['content'] ?? '',
                'post_status' => $postStatus,
                'post_type' => $postType,
            ];

            // カテゴリを追加（wp.newPost ではカテゴリ名の配列を terms_names で指定）
            if (!empty($payload['categories']) && is_array($payload['categories'])) {
                $content['terms_names'] = [
                    'category' => $payload['categories'],
                ];
            }

            Log::info('WP_XMLRPC_WP_NEWPOST_REQUEST', [
                'shop_id' => $shop->id,
                'endpoint' => $endpoint,
                'post_type' => $postType,
                'post_status' => $postStatus,
            ]);

            // wp.newPost を呼び出し
            $result = $this->sendXmlRpcRequest(
                $endpoint,
                'wp.newPost',
                [
                    $blogId,
                    $username,
                    $appPassword,
                    $content,
                ]
            );

            if (!$result || ($result['fault'] ?? false)) {
                $faultCode = $result['faultCode'] ?? null;
                $faultString = $result['faultString'] ?? 'Unknown error';
                
                Log::error('WP_XMLRPC_WP_NEWPOST_FAILED', [
                    'shop_id' => $shop->id,
                    'faultCode' => $faultCode,
                    'faultString' => $faultString,
                    'endpoint' => $endpoint,
                ]);
                return null;
            }

            $postId = $result['result'] ?? null;

            Log::info('WP_XMLRPC_WP_NEWPOST_SUCCESS', [
                'shop_id' => $shop->id,
                'wp_post_id' => $postId,
            ]);

            return [
                'id' => $postId,
            ];
        } catch (\Exception $e) {
            Log::error('WP_XMLRPC_WP_NEWPOST_EXCEPTION', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }
}

