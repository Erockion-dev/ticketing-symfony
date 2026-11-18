<?php

namespace App\Form;

use App\Entity\Ticket;
use App\Enum\TicketPriority;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TicketOperatorUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $ticket = $builder->getData();

        $builder
            ->add('priority', EnumType::class, [
                'class' => TicketPriority::class,
                'choice_label' => fn (TicketPriority $priority) => $priority->value,
            ])
            ->add('status',  ChoiceType::class, [
                'mapped' => false,
                'required' => false,
                'placeholder' => 'Current status: ' . $ticket->getStatus()->value,
                'choices' => $options['status_choice']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ticket::class,
            'status_choice' => [],
        ]);
    }
}
