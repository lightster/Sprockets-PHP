<?php
namespace Asset;

class File
{
	private $filepath, $path, $directory, $name, $type, $filters;

	public function __construct($path)
	{
		$pipeline = Pipeline::getCurrentInstance();
	
		$this->path = $path;
		$this->directory = '.' === ($dirname = dirname($path)) ? '' : $dirname;
		
		$file = basename($path);
		$filename_parts = explode('.', $file);
		$this->name = $filename_parts[0];
		$this->type = $filename_parts[1];
		$this->filters = array_slice($filename_parts, 2);
		
		$this->path_with_simple_filename = ('' === $this->directory ? '' : $this->directory . '/') . $this->name;
		$this->filepath = $pipeline->getFile($this->path_with_simple_filename, $this->type);

		$pipeline->addDependency($this->filepath);
	}
	
	private function getProcessedContent()
	{
		$content = self::processFilters($this->filepath, $this->filters);
		$new_content = '';
		
		foreach (explode("\n", $content) as $line)
		{
			if (substr($line, 0, 3) == '//=' || substr($line, 0, 2) == '#=')
			{
				$directive = explode(' ', trim(substr($line, 3)));
				
				$function = $directive[0];
				$arguments = array_slice($directive, 1);
				
				$new_content .= call_user_func_array(array($this, pascalize($function) . 'Directive'), $arguments) . "\n";
			}
			else
				$new_content .= $line . "\n";
		}
		
		return $new_content;
	}	

	public function process()
	{
		if (Pipeline::getCurrentInstance()->hasProcessedFile($this->filepath))
			return; //hasProcessedFile will add it otherwise

		return $this->getProcessedContent();
	}
	
	public function __toString()
	{
		try {
			return $this->process();
		} catch (Exception\Asset $e) {
			exit('Asset exception : ' . $e->getMessage());
		} catch (\Exception $e) {
			exit('External exception : ' . $e->getMessage());
		}
	}
	
	
	private function requireDirective($name)
	{
		$pipeline = Pipeline::getCurrentInstance();

		if ($pipeline->hasFile($file = $this->directory . $name, $this->type))
			return (string) new File($file . '.' . $this->type);
		else if ($pipeline->hasFile($file = $this->directory . $name . '/index', $this->type))
			return (string) new File($file . '.' . $this->type);
		else
			throw new Exception\FileNotFound($file, $type);
	}
	private function requireTreeDirective($name = '/')
	{
		return (string) new Tree($this->directory . $name, $this->type);
	}
	private function requireDirectoryDirective($name = '/')
	{
		return (string) new Directory($this->directory . $name, $this->type);
	}
	private function dependsOnDirective($name)
	{ //allows to depend on a file, even if this one isn't included
	}
	
	
	static private function processFilters($path, $filters)
	{
		return file_get_contents($path);
	}
}