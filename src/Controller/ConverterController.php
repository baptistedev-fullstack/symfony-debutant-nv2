<?php

namespace App\Controller;

use App\Conversion\UnitConverter;
use App\Conversion\UnsupportedConversionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class ConverterController extends AbstractController
{
    #[Route('/', name: 'app_converter', methods: ['GET'])]
    public function index(
        UnitConverter $converter,
        #[MapQueryParameter] ?string $valeur = null,
        #[MapQueryParameter] ?string $de = null,
        #[MapQueryParameter] ?string $vers = null,
    ): Response {
        $units = $converter->units();
        $knownUnits = array_merge(...array_values($units));

        $context = ['units' => $units, 'valeur' => null, 'de' => null, 'vers' => null, 'result' => null, 'error' => null];

        if (null === $valeur && null === $de && null === $vers) {
            return $this->render('converter/index.html.twig', $context);
        }

        // Seules des valeurs validées retournent dans la page : la saisie brute n'y est jamais réécrite.
        $number = filter_var($valeur, \FILTER_VALIDATE_FLOAT);
        if (false !== $number) {
            $context['valeur'] = trim($valeur);
        }
        $context['de'] = \in_array($de, $knownUnits, true) ? $de : null;
        $context['vers'] = \in_array($vers, $knownUnits, true) ? $vers : null;

        if (false === $number || null === $context['de'] || null === $context['vers']) {
            $context['error'] = 'Indiquez un nombre valide et deux unités.';

            return $this->render('converter/index.html.twig', $context, new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        try {
            $context['result'] = $converter->convert($number, $context['de'], $context['vers']);
        } catch (UnsupportedConversionException) {
            $context['error'] = 'Cette conversion n\'est pas possible.';

            return $this->render('converter/index.html.twig', $context, new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $this->render('converter/index.html.twig', $context);
    }
}
