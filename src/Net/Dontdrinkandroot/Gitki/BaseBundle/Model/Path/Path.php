<?php


namespace Net\Dontdrinkandroot\Gitki\BaseBundle\Model\Path;


interface Path
{

    /**
     * @return string
     */
    function getName();

    /**
     * @return bool
     */
    function hasParentPath();

    /**
     * @return DirectoryPath
     */
    function getParentPath();

    /**
     * @return string
     */
    function toString();

} 