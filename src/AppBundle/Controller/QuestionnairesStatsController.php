<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Participant;
use AppBundle\Entity\ParticipantAnswer;
use AppBundle\Entity\Question;
use AppBundle\Entity\Questionnaire;
use AppBundle\Stats\AnswerStat;
use AppBundle\Stats\QuestionStat;
use Doctrine\DBAL\Exception\ServerException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QuestionnairesStatsController extends Controller
{
    /**
     * @Method("GET")
     * @Route("/questionnaires-stats/{id}", name="questionnaires_stats")
     */
    public function questionnaireViewStatsActions($id)
    {
        $questionnaire = $this->getQuestionnaire($id);
        return $this->render('questionnaires/stats.html.twig', array(
            'questionnaire' => $questionnaire
        ));
    }

    /**
     * @Method("GET")
     * @Route("/questionnaires-stats/{id}/json")
     */
    public function getStats($id)
    {

        $questionnaire = $this->getQuestionnaire($id);
        $participants = $questionnaire->getParticipants();

        $questions = array();
        foreach ($questionnaire->getQuestions() as $question) {
            array_push($questions, new QuestionStat($question, $this->getQuestionStats($question, $participants)));
        }

        $serializer = $this->container->get('serializer');
        $questions = $serializer->serialize($questions, 'json');

        return new JsonResponse($questions);
    }

    public function getQuestionStats(Question $question, $participants)
    {
        $complete = array();
        foreach ($question->getAnswers() as $answer) {
            $trueCount = 0;
            $falseCount = 0;
            foreach ($participants as $participant) {
                foreach ($participant->getAnswers() as $participantAnswer) {
                    if ($participantAnswer->getAnswer()->getId() == $answer->getId()) {

                        if ($participantAnswer->getChecked() == true) {
                            $trueCount++;
                        } else {
                            $falseCount++;
                        }

                        if ($question->getType() == 'SINGLE') {
                            break;
                        }
                    }
                }
            }
            array_push($complete, new AnswerStat($trueCount, $falseCount, $answer->getContent()));
        }
        return $complete;
    }

    public function getQuestionnaireAnswers($questionnaire)
    {
        $qb = $this->getDoctrine()->getManager()->createQueryBuilder();

        $qb->select('a')
            ->from('AppBundle:Answer', 'a')
            ->join('a.question', 'qu')
            ->join('qu.questionnaire', 'q')
            ->where('q.id = ?1')
            ->setParameter(1, $questionnaire->getId());

        return $qb->getQuery()->getResult();
    }

    public function getQuestionnaire($id)
    {
        $userId = $this->get('security.token_storage')->getToken()->getUser()->getId();

        $qb = $this->getDoctrine()->getManager()->createQueryBuilder();
        $qb->select('q')
            ->from('AppBundle:Questionnaire', 'q')
            ->join('q.user', 'u')
            ->where('q.visible = true')
            ->andWhere('q.id = ?1')
            ->andWhere('q.expires > ?2')
            ->andWhere('u.id = ?3')
            ->setParameter(1, $id)
            ->setParameter(2, new \DateTime())
            ->setParameter(3, $userId);

        $result = $qb->getQuery()->getSingleResult();

        if ($result == null) {
            throw new NotFoundHttpException("No such questionnaire found under the current user");
        }
        return $result;
    }
}