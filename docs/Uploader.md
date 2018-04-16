# Uploader 
Сервис(Каллбек) который позволяет загружать данные из итератовра в dataStore.
В качестве зависимостей ему нужен обьект тип `IteratorAggregator` который вернет итератор для 
итерирования по данным, и DataStore как конечное хранилище. 

Давайте разберем простейший пример, загрузки данных из одног DataStore в другой
> DataStore являеться IteratorAggregator, по этому мы можем использовать 
его в качестве источника дныных
```php
<?
use \rollun\datastore\DataStore;
use \rollun\uploader\Uploader;
//Создадим простейший Memory DataStore
$sourceDataStore = new DataStore\Memory();
//Заполним его произвольными данными
for ($i = 0; $i < 1000; $i++) {
    $sourceDataStore->create([
        "id" => uniqid("id_"),
        "name" => uniqid("name_"),
        "age" => uniqid("age_"),
    ]);
}

//Создадим теперь destination DataStore
$destinationDataStore = new DataStore\Memory();

//Тпереь давайте попробуем загрузить данные из первого во второй.

//Создадим обьект Uploader.
$uploaderCallback = new Uploader($sourceDataStore, $destinationDataStore);

//Загрузим данные
$uploaderCallback->upload();
//так же можно вызвать метод __invoke()

//Тпереь давайде проверим что оба DataStore имеют одни и теже данные.
$sourceItr = $sourceDataStore->getIterator();
$destinationItr = $destinationDataStore->getIterator();
while ($sourceItr->valid() && $destinationItr->valid()) {
    $item = $destinationItr->current();
    foreach ($sourceItr->current() as $filed => $value) {
        if($item[$filed] != $value) {
            throw new RuntimeException("Data not equals");
        }
    }
    $sourceItr->next();
    $destinationItr->next();
}
``` 
Давайте представим теперь ситуацию что у нас есть источник данных, которй долго ищет данные.
И попробуем загрузить данные с него
```php
<?php
use \rollun\datastore\DataStore;
use \rollun\uploader\Uploader;
use \Xiag\Rql\Parser\Query;

//Реализуем псевдо класс который будет имитировать долгий поиск данных
$sourceLazyDataStore = new class extends DataStore\Memory {
    
    /**
     * Lazy query. Sleep 5 second before search
     * @param Query $query
     * @return array
     */
    public function query(Query $query)
    {
        usleep(100);//Sleep for imitate slow search
        return parent::query($query);
    }
};

//Заполним его произвольными данными
for ($i = 0; $i < 1000; $i++) {
    $sourceLazyDataStore->create([
        "id" => uniqid("id_"),
        "name" => uniqid("name_"),
        "age" => uniqid("age_"),
    ]);
}

//Создадим теперь destination DataStore
$destinationDataStore = new DataStore\Memory();

//Тпереь давайте попробуем загрузить данные из первого во второй.

//Создадим обьект Uploader.
$uploaderCallback = new Uploader($sourceLazyDataStore, $destinationDataStore);

//Загрузим данные.
//Но давайте допольнительно посчитаем время которое уйдет на загрузку
$startTime = microtime(true);
$uploaderCallback->upload();
$endTime = microtime(true);
$uploadTime = $endTime - $startTime;
//Выведем время 
echo $uploadTime;
//Очевидно это не лучший результат...
```
Очевидно у нас вышел не лучший результат...
Давайте попробуем это исправить. Использум `rollun\Uploader\Iterator\DataStorePackIterator` в качестве итератора DataStore.
Взглянем на код.

```php
<?php
use \rollun\datastore\DataStore;
use \rollun\uploader\Uploader;
use \Xiag\Rql\Parser\Query;
use \rollun\Uploader\Iterator;

//Реализуем псевдо класс который будет имитировать долгий поиск данных
$sourceLazyDataStore = new class extends DataStore\Memory {
    
    /**
     * Lazy query. Sleep 5 second before search
     * @param Query $query
     * @return array
     */
    public function query(Query $query)
    {
        usleep(100);//Sleep for imitate slow search
        return parent::query($query);
    }
    
    /**
     * Return special iterator
     * @return Iterator\DataStorePack
     */
    public function getIterator()
    {
        return new Iterator\DataStorePack($this);
    }
};

//Заполним его произвольными данными
for ($i = 0; $i < 1000; $i++) {
    $sourceLazyDataStore->create([
        "id" => uniqid("id_"),
        "name" => uniqid("name_"),
        "age" => uniqid("age_"),
    ]);
}

//Создадим теперь destination DataStore
$destinationDataStore = new DataStore\Memory();

//Тпереь давайте попробуем загрузить данные из первого во второй.

//Создадим обьект Uploader.
$uploaderCallback = new Uploader($sourceLazyDataStore, $destinationDataStore);

//Загрузим данные.
//Но давайте допольнительно посчитаем время которое уйдет на загрузку
$startTime = microtime(true);
$uploaderCallback->upload();
$endTime = microtime(true);
$uploadTime = $endTime - $startTime;
//Выведем время 
echo $uploadTime;
```
Стало намного лучше. 
Но это еще не все что умеет наш итератор. 
Давайте представим ситуацию что наш источник(source) не имеет сразу все данные, а получает их со временем.
С обычным итератором, мы мы не смогли продолжить загрузку данных, но с `` мы можем это сделать.


```php
<?php
use \rollun\datastore\DataStore;
use \rollun\uploader\Uploader;
use \Xiag\Rql\Parser\Query;
use \rollun\Uploader\Iterator;

//Реализуем псевдо класс который будет имитировать долгий поиск данных
$sourceDataStore = new class extends DataStore\Memory {
    
    /**
     * Lazy query. Sleep 5 second before search
     * @param Query $query
     * @return array
     */
    public function query(Query $query)
    {
        return parent::query($query);
    }
    
    /**
     * Return special iterator
     * @return Iterator\DataStorePack
     */
    public function getIterator()
    {
        return new Iterator\DataStorePack($this);
    }
};
//Создадим теперь destination DataStore
$destinationDataStore = new DataStore\Memory();
//Создадим обьект Uploader.
$uploaderCallback = new Uploader($sourceDataStore, $destinationDataStore);


for($part = 1; $part < 5; $part++) {
    //Заполним его произвольными данными
    for ($i = 0; $i < 500; $i++) {
        $sourceDataStore->create([
            "id" => uniqid("id_"),
            "name" => uniqid("name_"),
            "age" => uniqid("age_"),
        ]);
    }
    //Тпереь давайте попробуем загрузить часть данных
    $uploaderCallback->upload();
}

//Тпереь давайде проверим что оба DataStore имеют одни и теже данные.
$sourceItr = $sourceDataStore->getIterator();
$destinationItr = $destinationDataStore->getIterator();
while ($sourceItr->valid() && $destinationItr->valid()) {
    $item = $destinationItr->current();
    foreach ($sourceItr->current() as $filed => $value) {
        if($item[$filed] != $value) {
            throw new RuntimeException("Data not equals");
        }
    }
    $sourceItr->next();
    $destinationItr->next();
}
```

Как видите, нам удалось загрузить даные из источника частями.