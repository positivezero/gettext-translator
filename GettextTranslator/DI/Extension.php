<?php

namespace GettextTranslator\DI;

use Nette;


if (!class_exists('Nette\DI\CompilerExtension')) {
	class_alias('Nette\Config\CompilerExtension', 'Nette\DI\CompilerExtension');
}


class Extension extends Nette\DI\CompilerExtension
{
	/** @var array */
	private $defaults = array(
		'lang' => 'en',
		'files' => array(),
		'layout' => 'horizontal',
		'height' => 450
	);


	public function loadConfiguration()
	{
		$config = $this->getConfig($this->defaults);
		if (count($config['files']) === 0) {
			throw new InvalidConfigException('At least one language file must be defined.');
		}

		$builder = $this->getContainerBuilder();

		$translator = $builder->addDefinition($this->prefix('translator'));
		$translator->setClass('GettextTranslator\Gettext', array('@session', '@cacheStorage', '@httpResponse'));
		$translator->addSetup('setLang', array($config['lang']));
		$translator->addSetup('setProductionMode', array($builder->expand('%productionMode%')));

		$fileManager = $builder->addDefinition($this->prefix('fileManager'));
		$fileManager->setClass('GettextTranslator\FileManager');

		foreach ($config['files'] as $id => $file) {
			$translator->addSetup('addFile', array($file, $id));
		}

		$translator->addSetup('GettextTranslator\Panel::register', array('@application', '@self', '@session', '@httpRequest', $config['layout'], $config['height']));
	}

}


class InvalidConfigException extends Nette\InvalidStateException {

}
