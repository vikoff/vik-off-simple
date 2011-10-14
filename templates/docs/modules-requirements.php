<h3>Требования к модулям</h3>

<p>
	Модуль - это элемент системы, который может быть вызван для отображения основного контента сайта.
</p>
<p>
	Каждый модуль должен иметь обязательный набор файлов:
	<ul>
		<li>
			<b>Файл конфигурации config.php</b><br />
			В этом файле обязательно должен быть описан главный контроллер модуля.
		</li>
		<li>
			<b>Главный контроллер <code>class ModuleName_Controller</code></b><br />
			Этот контроллер будет вызван при обращении системы к модулю.
		</li>
	</ul>
</p>

<p>
	Основные требования к классам модуля:
	<ol>
		<li>
			В каждом классе контроллера и модели должна быть определена константа <code>MODULE</code>,
			содержащая имя модуля. (имя модуля должно быть указано с маленькой буквы)
		</li>
	</ol>
