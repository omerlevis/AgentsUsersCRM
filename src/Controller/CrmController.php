<?php
namespace App\Controller;

use App\Entity\Users;
use App\Repository\LogsRepository;
use App\Repository\UsersRepository;
use http\Client\Curl\User;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use function MongoDB\BSON\toJSON;

class CrmController extends AbstractController
{

    /**
     * @Route("/finantick-crm", name="home_page")
     */
    public function home_page(UsersRepository $Users_Rep): Response
    {

        $results = DB::table('calls')->select('id', 'pro_phone', 'customer_phone')->get();
        $json = $results->pluck('pro_phone', 'id')->toJson();
        echo $json;
        die();
        $currentUserEntity = $this->getUser();
        if (!empty($currentUserEntity)) {

            $current_user_id = $currentUserEntity->getId();
            $current_user_role = $Users_Rep->getUserRole($current_user_id);

            if (!empty($current_user_role)) {
                $users_to_display = $Users_Rep->findUsers($current_user_id, $current_user_role[0]['role']);
                $agent_to_display = $Users_Rep->findAgents($current_user_id, $current_user_role[0]['role']);
                $logs_to_display = $Users_Rep->findLogs();
                $logscounting_to_display = $Users_Rep->countLogs($current_user_id, $current_user_role[0]['role']);
            }
            else {
                $users_to_display =null;
                $agent_to_display = null;
                $logs_to_display = null;
                $logscounting_to_display = null ;
            }
        }
        else {
            $users_to_display = null;
            $agent_to_display = null;
            $logs_to_display = null;
            $logscounting_to_display = null ;
        }

        return $this->render('home_page.html.twig', [
            'users_to_display' =>$users_to_display,
            'agents_to_display' =>$agent_to_display,
            'logs_to_display' =>$logs_to_display,
            'logscounting_to_display' => $logscounting_to_display
        ]);
    }

    /**
     * @Route("/finantick-crm/edit/user_id={ids}%agent_id={agent}", name="edit_agents")
     */
    public function editAgents($ids ,$agent,UsersRepository $Users_Rep): Response
    {
        $ids_arr = explode(",",$ids);
        foreach ($ids_arr as $id) {
        $Users_Rep->updateAgents($id, $agent);

    }
        return $this->redirectToRoute('home_page');
    }

    /**
     * @Route("/finantick-crm/userlogin/user_id={id}", name="user_login")
     */
    public function UserLogin($id,UsersRepository $Users_Rep, LogsRepository $LogRep) : Response
    {
        $LogRep->updateLoginLog($id);
        $Users_Rep->updateLastLogin($id);
        return $this->redirectToRoute('home_page');

    }

}