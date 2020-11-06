<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\DoctrineForms\Controls;

use Doctrine\Common\Collections\Collection;
use Kdyby;
use Kdyby\DoctrineForms\EntityFormMapper;
use Kdyby\DoctrineForms\IComponentMapper;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nette;
use Nette\ComponentModel\Component;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ToOne implements IComponentMapper
{
	use \Nette\SmartObject;

	/**
	 * @var EntityFormMapper
	 */
	private $mapper;

	/**
	 * @var Kdyby\Doctrine\EntityManager
	 */
	private $em;

	public function __construct(EntityFormMapper $mapper)
	{
		$this->mapper = $mapper;
		$this->em = $this->mapper->getEntityManager();
	}



	/**
	 * {@inheritdoc}
	 */
	public function load(ClassMetadata $meta, Component $component, $entity)
	{
		if (!$component instanceof Nette\Forms\Container) {
			return FALSE;
		}

		if (!$relation = $this->getRelation($meta, $entity, $component->getName())) {
			return FALSE;
		}

		$this->mapper->load($relation, $component);

		return TRUE;
	}



	/**
	 * {@inheritdoc}
	 */
	public function save(ClassMetadata $meta, Component $component, $entity)
	{
		if (!$component instanceof Nette\Forms\Container) {
			return FALSE;
		}

		if (!$relation = $this->getRelation($meta, $entity, $component->getName())) {
			return FALSE;
		}

		if (! $relation->getId()) {
			// doctrine never calls constructor,
			// we have to create the instance on ourself
			$relation = new $relation;
		}
		else {
			if (
				$component instanceof Kdyby\DoctrineForms\ToOneContainer
				&&
				$component->isAllowedRemove()
				&&
				!array_filter($component->getValues('array'))
			) {
				$meta->setFieldValue($entity, $component->getName(), null);
				return true;
			}
		}

		$this->mapper->save($relation, $component);

		if (! $relation->getId()) {
			$this->em->persist($relation);
			$meta->setFieldValue($entity, $component->getName(), $relation);
		}

		return TRUE;
	}



	/**
	 * @param ClassMetadata $meta
	 * @param object $entity
	 * @param string $field
	 * @return bool|object
	 */
	private function getRelation(ClassMetadata $meta, $entity, $field)
	{
		if (!$meta->hasAssociation($field) || !$meta->isSingleValuedAssociation($field)) {
			return FALSE;
		}

		// todo: allow access using property or method
		$relation = $meta->getFieldValue($entity, $field);
		if ($relation instanceof Collection) {
			return FALSE;
		}

		if ($relation === NULL) {
			$class = $meta->getAssociationTargetClass($field);
			$relationMeta = $this->mapper->getEntityManager()->getClassMetadata($class);

			$relation = $relationMeta->newInstance();
			$meta->setFieldValue($entity, $field, $relation);
		}

		return $relation;
	}

}
