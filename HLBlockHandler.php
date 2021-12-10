<?php

namespace Namespase;
/**
 * Универсальный класс для работы с hightload блоками
 */
class HLBlockHandler
{
    /** @var int $hlBlockId id hightload блока */
    public $hlBlockId; 
    
    /** @var string $hlDataClass id hightload блока */
    protected $hlDataClass;

    public function __construct(string $hlBlockId)
    {
        $this->hlBlockId = $hlBlockId;

        if(!\CModule::IncludeModule("highloadblock"))
        {
			throw new \Bitrix\Main\LoaderException("Отсутствует модуль highloadblock");
        }
            
        $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getById($this->hlBlockId)->fetch();

        if (empty($hlblock))
        {
            throw new \Bitrix\Main\ObjectNotFoundException("Не удалось найти highload блок с id = " . $this->hlBlockId);
        }

        $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
        
        $this->hlDataClass = $entity->getDataClass();
    }

    /**
     * Получение записей из hl блока с помощью массива параметров getList
     *
     * @param Array $params Массив аргументов вида ["select"=>["UF_NAME", "UF_VALUE"],"order"=>["UF_NAME" => "ASC"], "limit"=>2, "filter"=>[UF_ACTIVE" => "1"] ]
     * @return Array Массив выборки из hl блока
     **/
    public function get(Array $params) :Array
    {
        $rsData = $this->hlDataClass::getList($params);
        $res = [];
        
        while($el = $rsData->fetch()){
            $res[] = $el;
        }
        
        return $res;
    }

    /**
     * Добавление записи в hl блок с помощью массива параметров add
     *
     * @param Array $fields Массив аргументов вида ["UF_NAME"=>"UF_VALUE"]
     * @return void
     **/
    public function add(Array $fields) :void
    {
        $this->hlDataClass::add($fields);
    }
}