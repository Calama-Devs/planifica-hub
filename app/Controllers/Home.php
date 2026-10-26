<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        $data = [
            'title' => 'Início | PlanIFica Hub',
        ];
        $data['content'] = view('home', $data);

        return view('dashboard', $data);
    }
}
