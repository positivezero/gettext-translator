<?php

namespace GettextTranslator;

use Nette;
use Nette\Utils\Strings;


class FileManager extends Nette\Object
{
	/** @var array { [ key => default ] } */
	private $defaultMetadata = array(
		'Project-Id-Version' => '',
		'Report-Msgid-Bugs-To' => NULL,
		'POT-Creation-Date' => '',
		'Last-Translator' => '',
		'Language-Team' => '',
		'MIME-Version' => '1.0',
		'Content-Type' => 'text/plain; charset=UTF-8',
		'Content-Transfer-Encoding' => '8bit',
		'Plural-Forms' => 'nplurals=3; plural=((n==1) ? 0 : (n>=2 && n<=4 ? 1 : 2));',
		'X-Poedit-Language' => NULL,
		'X-Poedit-Country' => NULL,
		'X-Poedit-SourceCharset' => NULL,
		'X-Poedit-KeywordsList' => NULL
	);


	/**
	 * @param string
	 * @param array
	 * @return array
	 */
	public function generateMetadata($identifier, $currentMetadata)
	{
		$result = array();
		$result[] = 'PO-Revision-Date: ' . date('Y-m-d H:iO');

		foreach ($this->defaultMetadata as $key => $default) {
			if (isset($currentMetadata[$identifier][$key])) {
				$result[] = $key . ': ' . $currentMetadata[$identifier][$key];

			} elseif ($default) {
				$result[] = $key . ': ' . $default;
			}
		}

		return $result;
	}


	/**
	 * @param string
	 * @param string
	 * @param array
	 * @return array
	 */
	public function parseMetadata($input, $identifier, $metadata = array())
	{
		$input = trim($input);

		$input = preg_split('/[\n,]+/', $input);
		foreach ($input as $value) {
			$pattern = ': ';
			$tmp = preg_split("($pattern)", $value);
			$metadata[$identifier][trim($tmp[0])] = count($tmp) > 2 ? ltrim(strstr($value, $pattern), $pattern) : $tmp[1];
		}

		return $metadata;
	}


	/**
	 * @param string
	 * @param string
	 * @param array
	 * @param array
	 * @param string
	 */
	public function buildPOFile($file, $identifier, $metadata, $dictionary, $newStrings)
	{
		$po = "# Gettext keys exported by GettextTranslator and Translation Panel\n" . "# Created: " . date('Y-m-d H:i:s') . "\n" . 'msgid ""' . "\n" . 'msgstr ""' . "\n";
		$po .= '"' . implode('\n"' . "\n" . '"', $metadata) . '\n"' . "\n\n\n";

		foreach ($dictionary as $message => $data) {
			if ($data['file'] !== $identifier) {
				continue;
			}

			$po .= 'msgid "' . str_replace(array('"'), array('\"'), $message) . '"' . "\n";

			if (is_array($data['original']) && count($data['original']) > 1) {
				$po .= 'msgid_plural "' . str_replace(array('"'), array('\"'), end($data['original'])) . '"' . "\n";
			}

			if (!is_array($data['translation'])) {
				$po .= 'msgstr "' . str_replace(array('"'), array('\"'), $data['translation']) . '"' . "\n";

			} elseif (count($data['translation']) < 2) {
				$po .= 'msgstr "' . str_replace(array('"'), array('\"'), current($data['translation'])) . '"' . "\n";

			} else {
				$i = 0;
				foreach ($data['translation'] as $string) {
					$po .= 'msgstr[' . $i . '] "' . str_replace(array('"'), array('\"'), $string) . '"' . "\n";
					$i++;
				}
			}

			$po .= "\n";
		}

		if (count($newStrings)) {
			foreach ($newStrings as $original) {
				if (trim(current($original)) != "" && !\array_key_exists(current($original), $dictionary)) {
					$po .= 'msgid "' . str_replace(array('"'), array('\"'), current($original)) . '"' . "\n";

					if (count($original) > 1) {
						$po .= 'msgid_plural "' . str_replace(array('"'), array('\"'), end($original)) . '"' . "\n";
					}

					$po .= "msgstr \"\"\n";
					$po .= "\n";
				}
			}
		}

		file_put_contents($file, $po);
	}


	/**
	 * @param string
	 * @param string
	 * @param array
	 * @param array
	 */
	public function buildMOFile($file, $identifier, $metadata, $dictionary)
	{
		$dictionary = array_filter($dictionary, function ($data) use ($identifier) {
			return $data['file'] === $identifier;
		});

		ksort($dictionary);

		$metadata = implode("\n", $metadata);

		$items = count($dictionary) + 1;
		$ids = Strings::chr(0x00);
		$strings = $metadata . Strings::chr(0x00);
		$idsOffsets = array(0, 28 + $items * 16);
		$stringsOffsets = array(array(0, strlen($metadata)));

		foreach ($dictionary as $key => $value) {
			$id = $key;
			if (is_array($value['original']) && count($value['original']) > 1) {
				$id .= Strings::chr(0x00) . end($value['original']);
			}

			$string = implode(Strings::chr(0x00), $value['translation']);
			$idsOffsets[] = strlen($id);
			$idsOffsets[] = strlen($ids) + 28 + $items * 16;
			$stringsOffsets[] = array(strlen($strings), strlen($string));
			$ids .= $id . Strings::chr(0x00);
			$strings .= $string . Strings::chr(0x00);
		}

		$valuesOffsets = array();
		foreach ($stringsOffsets as $offset) {
			list ($all, $one) = $offset;
			$valuesOffsets[] = $one;
			$valuesOffsets[] = $all + strlen($ids) + 28 + $items * 16;
		}
		$offsets = array_merge($idsOffsets, $valuesOffsets);

		$mo = pack('Iiiiiii', 0x950412de, 0, $items, 28, 28 + $items * 8, 0, 28 + $items * 16);
		foreach ($offsets as $offset) {
			$mo .= pack('i', $offset);
		}

		file_put_contents($file, $mo . $ids . $strings);
	}

}
