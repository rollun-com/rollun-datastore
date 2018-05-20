<?php

namespace rollun\test\files\FileObject\StringOperations;

use rollun\test\files\FileObject\AbstractTest;

abstract class StringOperationsAbstractTest extends AbstractTest
{

    protected function specialStringsPrvider()
    {
        return $specialStrings = array(
            'empty' => '',
            'space' => ' ',
            'tab' => "\t",
            'en' => 'qwerty 123 \t TAB `~!@#$%^&&*(){}[];:\'" "" """<>?.,/\ \\ =-+ ASDF',
            'ru' => 'йцукенгшщзхъэждлорпавыфячсмитьбю ЙЦУКЕНГШЩЗХЪЭЖДЛОРПАВЫФЯЧСМИТЬБЮ'
        );
    }

    /**
     * [1,2,3] >> [[1,2,3],[2,3,1],[3,1,2]]
     *
     * @param array  $stringsArray
     * @param array $resultArray
     * @return type
     */
    protected function cyclicShifts($stringsArray, $resultArray = [])
    {
        $stringsArray = array_values($stringsArray);
        if (isset($resultArray[0]) && $resultArray[0] === $stringsArray) {
            return $resultArray;
        }
        $resultArray[] = $stringsArray;
        array_unshift($stringsArray, array_pop($stringsArray));
        $resultArray = $this->cyclicShifts($stringsArray, $resultArray);
        return $resultArray;
    }

    protected function stringsNumbersProvider(array $stringsArray)
    {
        $count = count($stringsArray);

        switch ($count) {
            case 0:
                throwException(new \InvalidArgumentException('Empty array'));
            case 1:
                return[0];
            case 2:
                return[0, 1];
            case 3:
                return[0, 1, 3];
        }
        return[0, 1, $count - 1, $count];
    }

    protected function getRndString($maxlength = 1000)
    {
        return str_repeat(chr(rand(20, 126)), rand(0, $maxlength));
    }

}
