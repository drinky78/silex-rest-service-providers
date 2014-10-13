<?php
namespace MRadossi\Service;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated since 1.0, will be replaced by a event driven setup
 */
class RestEntityService
{
    /**
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * @var \Silex\Application
     */
    protected $app;
    
    /**
     * @var string
     */
    protected $fieldNameIdentifier = 'id';

    /**
     * @param Request $request
     * @param Application $app
     */
    public function __construct(Request $request, Application $app)
    {
        $this->request = $request;
        $this->app = $app;
    }

    /**
     * @param $identifier
     * @return mixed
     */
    public function getAction($identifier)
    {
        $entity = $this->getEntityFromRepository($identifier);
        $this->isValidEntity($entity, $identifier);

        return $this->app['doctrine.extractor']->extractEntity(
            $entity,
            'detail'
        );
    }


    /**
     * @return array
     */
    public function getCollectionAction()
    {
        /*
        $repository = $this->app['service.request.filter']->filter(
            $this->getEntityRepository()
        );

        $page = $this->request->query->get('page');
        if(null === $page) {
            $page = 1;
        }

        $limit = $this->request->query->get('limit');
        if(null === $limit || !is_numeric($limit)) {
            $limit = 20;
        }

        $repository = $repository->paginate($page, $limit);

        return array(
            'data' => $this->app['doctrine.extractor']->extractEntities(
                $repository,
                'list'
            ),
            'pagination' => array(
                'page' => $page,
                'limit' => $limit,
                'total' => $repository->count()
            )
        );//*/
        $repository = $this->getEntityRepository();

        $page = $this->request->query->get('page');
        if(null === $page) {
            $page = 1;
        }

        $limit = $this->request->query->get('limit');
        if(null === $limit || !is_numeric($limit)) {
            $limit = 20;
        }

        $data = $repository->findBy(array(), array(), $limit, ($page-1)*$limit);
        
        return array(
            'data' => $this->app['doctrine.extractor']->extractEntities(
                $data,
                'list'
            ),
            'pagination' => array(
                'page' => $page,
                'limit' => $limit,
                'total' => count($data)
            )
        );
    }

    /**
     * @return array
     */
    public function getLinkedCollectionAction($id)
    {
        $fk = $this->request->attributes->get('fk');
        
        /*
        $repository = $this->app['service.request.filter']->filter(
            $repository = $this->getEntityRepository()
        );
        */
        $repository = $this->getEntityRepository();

        $page = $this->request->query->get('page');
        if(null === $page) {
            $page = 1;
        }

        $limit = $this->request->query->get('limit');
        if(null === $limit || !is_numeric($limit)) {
            $limit = 20;
        }

        $criteria = array($fk => $id);
        return array(
            'data' => $this->app['doctrine.extractor']->extractEntities(
                $repository->findBy($criteria, array(), $limit, ($page-1)*$limit),
                'list'
            ),
            'pagination' => array(
                'page' => $page,
                'limit' => $limit,
                'total' => $repository->count()
            )
        );
/*
        $repository = $repository->paginate($page, $limit);
        
        return array(
            'data' => $this->app['doctrine.extractor']->extractEntities(
                $repository,
                'list'
            ),
            'pagination' => array(
                'page' => $page,
                'limit' => $limit,
                'total' => $repository->count()
            )
        );
*/
    }
    
    /**
     * @param $identifier
     * @return array
     */
    public function deleteAction($identifier)
    {
        $entity = $this->getEntityFromRepository($identifier);
        $this->isValidEntity($entity, $identifier);

        $this->app['orm.em']->remove($entity);
        $this->app['orm.em']->flush();

        return true;
    }

    /**
     * @return array
     */
    public function postAction()
    {
        $entity = $this->app['doctrine.hydrator']->hydrateEntity(
            $this->request->getContent(),
            $this->getEntityName()
        );

        $response = $this->app['validator']->validate($entity);
        if(count($response) > 0) {
            return $this->formatErrors($response);
        }

        $this->app['orm.em']->persist($entity);
        $this->app['orm.em']->flush();

        return $this->app['doctrine.extractor']->extractEntity(
            $entity,
            'detail'
        );
    }

    /** 
     * @param $identifier
     * @return array
     */
    public function putAction($identifier)
    {
        $entity = $this->getEntityFromRepository($identifier);
        $this->isValidEntity($entity, $identifier);

        $updatedEntity = $this->app['doctrine.hydrator']->hydrateEntity(
            $this->request->getContent(),
            $this->getEntityName()
        );

        $response = $this->app['validator']->validate($updatedEntity);
        if(count($response) > 0) {
            return $this->formatErrors($response);
        }

        $this->app['orm.em']->merge($updatedEntity);
        $this->app['orm.em']->flush();

        return $this->app['doctrine.extractor']->extractEntity(
            $updatedEntity,
            'detail'
        );
    }


    /**
     * @param $id
     * @return mixed
     */
    public function getEntityFromRepository($id)
    {
        $repository = $this->getEntityRepository();

        $entity = $repository->findOneBy(
            array($this->getFieldNameIdentifier() => $id)
        );

        return $entity;
    }

    /**
     * @param $entity
     * @param $app
     * @param $id
     */
    public function isValidEntity($entity, $id)
    {
        if(null === $entity) {
            $this->app->abort(404, "$id does not exist.");
        }
    }

    /**
     * @return mixed
     */
    public function getEntityName()
    {
        return $this->app['doctrine.resolver']->getEntityClassName(
            $this->request->attributes->get('namespace'),
            $this->request->attributes->get('entity')
        );
    }

    /**
     * @return mixed
     */
    public function getEntityRepository()
    {
        return $this->app['orm.em']->getRepository(
            $this->getEntityName()
        );
    }
    
    /**
     * @return string
     */
    public function getFieldNameIdentifier()
    {
        return $this->fieldNameIdentifier;
    }

    /**
     * @param string $fieldNameIdentifier
     */
    public function setFieldNameIdentifier($fieldNameIdentifier)
    {
        $this->fieldNameIdentifier = $fieldNameIdentifier;
    }

    /**
     * @param $errors
     * @return array
     */
    protected function formatErrors($errors)
    {
        $errorFormatted = array();
        foreach($errors as $error) {
            $errorFormatted[$error->getPropertyPath()][] = $error->getMessage();
        }

        return array('errors' => $errorFormatted);
    }
}