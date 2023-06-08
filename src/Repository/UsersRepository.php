<?php

namespace App\Repository;

use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Users>
 *
 * @method Users|null find($id, $lockMode = null, $lockVersion = null)
 * @method Users|null findOneBy(array $criteria, array $orderBy = null)
 * @method Users[]    findAll()
 * @method Users[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UsersRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Users::class);
    }

    public function save(Users $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Users $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Users) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);

        $this->save($user, true);
    }

//    /**
//     * @return Users[] Returns an array of Users objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Users
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
    public function findUsers($current_user,$current_user_role)
    {
        $connection=$this->getEntityManager()->getConnection();
        if(strpos($current_user_role,'admin'))
        {
            $sql='select * from users order by id';
        }
        else {
        $sql = "with RECURSIVE CTE AS (
    select users.*, agents.id as agent_user_id
                 from users
                          left join agents on users.id = agents.user_id
                 where users.id = '{$current_user}'
                 UNION  ALL
                 select usr2.*, agnt2.id as agent_user_id
                 from users usr2
                          left join agents agnt2 on usr2.id = agnt2.user_id
                 INNER JOIN CTE ON CTE.agent_user_id=usr2.agent_id

                 )
SELECT id,username,login_time,date_created,COALESCE(agent_id,0) as agent_id,roles,agent_user_id FROM CTE where agent_user_id is null
order by id";
    }
        $statement =$connection->prepare($sql);
        $resultSet = $statement->executeQuery();
        return $resultSet->fetchAllAssociative();
    }

    public function findAgents($current_user,$current_user_role)
    {
        $connection=$this->getEntityManager()->getConnection();
        if(strpos($current_user_role,'admin'))
        {
            $sql='select agents.id,agents.username,role,user_id as agent_user_id,users.date_created,
       users.login_time,users.agent_id
from agents
         left join users on agents.user_id = users.id
order by agents.id';
        }
        else {
            $sql = "with RECURSIVE CTE AS (
    select users.*, agents.id as id_in_agents
    from users
             left join agents on users.id = agents.user_id
    where users.id = '{$current_user}'
    UNION  ALL
    select usr2.*, agnt2.id as agent_user_id
    from users usr2
             left join agents agnt2 on usr2.id = agnt2.user_id
             INNER JOIN CTE ON CTE.id_in_agents=usr2.agent_id

)
SELECT id,username,login_time,date_created,COALESCE(agent_id,0) as agent_id,roles,id_in_agents FROM CTE where id_in_agents is not null
order by id";
        }
        $statement =$connection->prepare($sql);
        $resultSet = $statement->executeQuery();
        return $resultSet->fetchAllAssociative();

    }

    public function updateAgents($user_id,$agent_id)
    {
        $connection=$this->getEntityManager()->getConnection();
        $sql = "update users set agent_id='{$agent_id}' where id='{$user_id}' ";
        $statement =$connection->prepare($sql);
        $resultSet = $statement->executeQuery();
        return $resultSet->fetchAllAssociative();

    }

    public function getUserRole($current_user_id)
    {
        $connection=$this->getEntityManager()->getConnection();
        $sql = "select role from agents where user_id='{$current_user_id}'";
        $statement =$connection->prepare($sql);
        $resultSet = $statement->executeQuery();
        return $resultSet->fetchAllAssociative();
    }

    public function updateLastLogin($id)
    {
        $connection=$this->getEntityManager()->getConnection();
        $sql = "update users set login_time=(select now()) where id='{$id}' ";
        $statement =$connection->prepare($sql);
        $resultSet = $statement->executeQuery();
        return $resultSet->fetchAllAssociative();

    }

    public function findLogs()
    {
        $connection=$this->getEntityManager()->getConnection();
        $sql = "select * from logs order by date_created desc ";
        $statement =$connection->prepare($sql);
        $resultSet = $statement->executeQuery();
        return $resultSet->fetchAllAssociative();
    }

    public function countLogs($current_user,$current_user_role)
    {
        $connection=$this->getEntityManager()->getConnection();
        if(strpos($current_user_role,'admin'))
        {
            $sql="select b.user_id,b.username from
(select logs.user_id,a.username,count(logs.user_id) as qty from
                                                               (select users.id,users.username from users left join agents on users.id = agents.user_id
                                                                         where agents.user_id is null) a
left join logs on a.id=logs.user_id
where logs.action_name='login' and logs.date_created> now() - interval 5 minute
group by logs.user_id,a.username) b
where b.qty>=2";
        }
        else {
            $sql = "select b.user_id,b.username from
(select logs.user_id,a.username,count(logs.user_id) as qty from (with RECURSIVE CTE AS (
    select users.*, agents.id as agent_user_id
    from users
             left join agents on users.id = agents.user_id
    where users.id = '{$current_user}'
    UNION  ALL
    select usr2.*, agnt2.id as agent_user_id
    from users usr2
             left join agents agnt2 on usr2.id = agnt2.user_id
             INNER JOIN CTE ON CTE.agent_user_id=usr2.agent_id

)
SELECT id,username FROM CTE where agent_user_id is null
order by id) a
left join logs on a.id=logs.user_id
where logs.action_name='login' and logs.date_created> now() - interval 5 minute
group by logs.user_id,a.username) b
where b.qty>=2 ";

        }
        $statement =$connection->prepare($sql);
        $resultSet = $statement->executeQuery();
        return $resultSet->fetchAllAssociative();

    }

    public function Calls()
    {
        $connection=$this->getEntityManager()->getConnection();
        $sql = "select * from calls";
        $statement =$connection->prepare($sql);
        $resultSet = $statement->executeQuery();
        return $resultSet->fetchAllAssociative();

    }
}
