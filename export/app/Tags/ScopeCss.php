<?php
namespace App\Tags;

use Statamic\Tags\Tags;

class ScopeCss extends Tags
{
    protected static $handle = 'scope_css';

    public function index(): string
    {
        $css   = (string) $this->params->get('css', '');
        $id    = (string) $this->params->get('id', '');
        $scope = $id ? '#id-' . $id : ':root';

        return str_replace(':root', $scope, $css);
    }
}
