<?php

namespace App\Livewire\Pages\Manager;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('layouts.manager')]
#[Title('Help')]
class Help extends Component
{
    public string $search = '';

    public function getFaqs(): array
    {
        return [
            [
                'question' => __('app.faq_sa_q1'),
                'answer'   => __('app.faq_sa_a1'),
                'category' => __('app.faq_cat_user_management'),
            ],
            [
                'question' => __('app.faq_sa_q2'),
                'answer'   => __('app.faq_sa_a2'),
                'category' => __('app.faq_cat_analytics'),
            ],
            [
                'question' => __('app.faq_sa_q3'),
                'answer'   => __('app.faq_sa_a3'),
                'category' => __('app.faq_cat_analytics'),
            ],
            [
                'question' => __('app.faq_sa_q4'),
                'answer'   => __('app.faq_sa_a4'),
                'category' => __('app.faq_cat_analytics'),
            ],
            [
                'question' => __('app.faq_sa_q5'),
                'answer'   => __('app.faq_sa_a5'),
                'category' => __('app.faq_cat_analytics'),
            ],
            [
                'question' => __('app.faq_sa_q6'),
                'answer'   => __('app.faq_sa_a6'),
                'category' => __('app.faq_cat_ai_security'),
            ],
            [
                'question' => __('app.faq_sa_q7'),
                'answer'   => __('app.faq_sa_a7'),
                'category' => __('app.faq_cat_ai_security'),
            ],
            [
                'question' => __('app.faq_sa_q8'),
                'answer'   => __('app.faq_sa_a8'),
                'category' => __('app.faq_cat_ai_security'),
            ],
            [
                'question' => __('app.faq_shared_q_password'),
                'answer'   => __('app.faq_shared_a_password'),
                'category' => __('app.faq_cat_account'),
            ],
        ];
    }

    public function filteredFaqs(): array
    {
        $faqs = $this->getFaqs();

        if (blank($this->search)) {
            return $faqs;
        }

        $q = strtolower($this->search);

        return array_values(array_filter($faqs, function ($faq) use ($q) {
            return str_contains(strtolower($faq['question']), $q)
                || str_contains(strtolower($faq['answer']), $q)
                || str_contains(strtolower($faq['category']), $q);
        }));
    }

    public function render()
    {
        return view('livewire.pages.manager.help', [
            'filteredFaqs' => $this->filteredFaqs(),
        ]);
    }
}
