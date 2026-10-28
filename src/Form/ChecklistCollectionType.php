<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Checklist kao prava lista (SPEC 8g.4): dodavanje, brisanje i redosled u pregledaču (assets/js/checklist-editor.js).
 * Prazne stavke se ignorišu, a redosled je onaj u kome su stavke poslate (position = redni broj).
 */
class ChecklistCollectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $rows = [];
            $position = 0;
            foreach ((array) $event->getData() as $key => $row) {
                $text = trim((string) ($row['text'] ?? ''));
                if ('' !== $text) {
                    // ključ ostaje isti (postojeća stavka se ne meša sa drugom), a pozicija prati redosled slanja
                    $rows[$key] = ['text' => mb_substr($text, 0, 255), 'position' => (string) $position++];
                }
            }
            $event->setData($rows);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'entry_type' => ChecklistEntryType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'delete_empty' => true,
            'by_reference' => false,
            'prototype' => true,
            'label' => 'Checklista',
            'required' => false,
        ]);
    }

    public function getParent(): string
    {
        return CollectionType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'checklist_collection';
    }
}
