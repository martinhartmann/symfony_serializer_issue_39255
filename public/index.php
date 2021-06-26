<?php


declare(strict_types = 1);

use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;

error_reporting(E_ALL);

require __DIR__."/../vendor/autoload.php";

/**
 * Regular class without the `\Traversable` implementation as it's referred
 * to in `\Symfony\Component\Serializer\Encoder\XmlEncoder::selectNodeType()`.
 */
final class Node
{
	public function __construct(
		public string $value,
		public array $subNodes = []
	)
	{
	}
}

/**
 * regular class plus the \Traversable
 *
 * Serializing this class causes the problem as the iteration will
 * always be preferred despite the existance of a serializer
 * handling this case.
 */
final class NodeTraversable
	implements IteratorAggregate
{
	public function __construct(
		public string $value,
		public array $subNodes = []
	)
	{
	}

	public function getIterator() : Generator
	{
		yield from $this->subNodes;
	}
}

/**
 * Just another class with the `\Traversable` but serializing
 * gets done before it is passed to the regular serializer.
 */
final class NodeFixedTraversable
	implements IteratorAggregate
{
	public function __construct(
		public string $value,
		public array $subNodes = []
	)
	{
	}

	public function getIterator() : Generator
	{
		yield from $this->subNodes;
	}
}

final class NodeNormalizer
	implements NormalizerInterface
{
	public function supportsNormalization($data, string $format = null)
	{
		return $data instanceof Node
			|| $data instanceof NodeTraversable
			|| $data instanceof NodeFixedTraversable;
	}

	/**
	 * @param Node|NodeTraversable|NodeFixedTraversable $object
	 * @param string|null $format
	 * @param array $context
	 *
	 * @return array|string
	 */
	public function normalize($object, string $format = null, array $context = []) : array|string
	{
		/**
		 * return a fixed value just for the sake of finishing
		 */
		if("id" === $object->value)
		{
			return "fixed_id";
		}

		if($object instanceof NodeFixedTraversable)
		{
			/**
			 * Fix the serialization process manually be prenormalizing the value
			 * and returning the normalized value.
			 */
			$serialized = $this->normalize($object->subNodes[0], $format, $context);
			return [
				"id" => $serialized,
			];
		}

		/**
		 * If no serialization happens manually the serializer goes crazy.
		 */
		return [
			"id" => $object->subNodes[0],
		];
	}
}

$data = [
	/**
	 * works
	 */
	new Node("root", [
		new Node("id"),
	]),
	/**
	 * works
	 */
	new NodeFixedTraversable("root", [
		new NodeFixedTraversable("id"),
	]),
	/**
	 * causes exception as it's normalizing incorrectly
	 */
	new NodeTraversable("root", [
		new NodeTraversable("id"),
	]),
];

$serializer = new Serializer([new NodeNormalizer()], [new XmlEncoder()]);
foreach($data as $item)
{
	try
	{
		$serialized = $serializer->serialize($item, "xml");
		var_dump([
			"serialized value ok" => $serialized === <<<XML
<?xml version="1.0"?>
<response><id>fixed_id</id></response>

XML,
		]);
	}
	catch(Throwable $e)
	{
		var_dump([
			"error" => "Exception on serializing '".$item::class."'",
			"message" => $e->getMessage(),
			"location" => "{$e->getFile()}:{$e->getLine()}",
			"serialized value ok" => false,
		]);
	}
}
