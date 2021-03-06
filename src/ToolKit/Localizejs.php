<?php
namespace Translation\ToolKit;

use GuzzleHttp\Client;
use Translation\TranslationMemory;

/**
 * This Class help make api request to Localizejs
 * website https://localizejs.com/
 * @author Nay Kang
 *
 */
class Localizejs{
    const END_POINT = 'https://api.localizejs.com';
    const TRANSLATOR_NAME = 'Localizejs';
    
    private $client = null;
    private $project_key = null;
    private $public_key = null;
    
    /**
     * Create new Localizejs Instance
     * @param string $public_key
     * @param string $project_key
     */
    public function __construct($public_key,$project_key){
        $this->project_key = $project_key;
        $this->public_key = $public_key;
        $this->client = new Client([
            'base_uri' => self::END_POINT
        ]);
    }
    
    /**
     * Get available translation list from localizejs
     * @param string $language_to_code
     * @return array 
     */
    public function getTranslations($language_to_code){
        $url = '/v2.0/projects/'.$this->project_key.'/translations';
        $result = $this->request('GET', $url,[
            'query' => [
                'language' => $this->convertLanguageCode($language_to_code),
            ]
        ]);
        $result = json_decode($result,true);
        return $result;
    }
    
    /**
     * Tell the content need to translate or verify to localizejs
     * @param string $content
     */
    public function createPharse($content){
        $url = 'v2.0/projects/'.$this->project_key.'/phrases';
        $this->request('POST', $url,[
            'headers' => [
                'content-type'=>'application/json'
            ],
            'body' => json_encode(['phrases'=>[$content]])
        ]);
    }
    
    /**
     * Get translation list from localizejs,and put it into Translation Memory
     * @param TranslationMemory $tm
     * @param string $language_to_code
     */
    public function refreshLocalStore(TranslationMemory $tm,$language_to_code){
        $translations = $this->getTranslations($language_to_code);
        foreach($translations['data']['translations'] as $trans){
            $tm->setConfig('to', $language_to_code);
            $tm->setConfig('from', 'en');//hard code,because localizejs only support english translate to other language
            $original = ltrim($trans['phrase'],'#');
            $tm->setTranslation($original, $trans['value'], self::TRANSLATOR_NAME);
        }
        return count($translations['data']['translations']);
    }
    
    /**
     * Get all translations from Transation Memory and filte out not approved by localizejs
     * then send back to localizejs
     * @param TranslationMemory $tm
     * @return number
     */
    public function createPhraseFromLocal(TranslationMemory $tm,$language_to_code){
        $tm->setConfig('to', $language_to_code);
        $list = $tm->getList();
        $count = 0;
        foreach($list as $v){
            if($v['translator']!=self::TRANSLATOR_NAME){
                $count++;
                $this->createPharse($v['original']);
            }
        }
        return $count;
    }
    
    /**
     * I thought localizejs do not support zh-tw,but it support
     * @param string $code
     * @throws \Exception
     */
    protected function convertLanguageCode($code){
        #if(strtolower($code)=='zh-tw'){
        #    throw new \Exception('Code:'.$code.' not support');
        #}
        #return substr($code, 0,2);
        return $code;
    }
    
    /**
     * Core request function
     * @param string $method
     * @param string $url
     * @param array $options
     */
    protected function request($method,$url,array $options=[]){
        if(isset($options['headers'])){
            $headers = $options['headers'];
        }else{
            $headers = [];
        }
        $headers = array_merge($headers,[
            'Authorization' => 'Bearer '.$this->public_key,
        ]);
        $options['headers'] = $headers;
        $response = $this->client->request($method,$url,$options);
        return $response->getBody()->getContents();
    }
}
