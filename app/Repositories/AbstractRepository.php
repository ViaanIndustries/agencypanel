<?PHP

namespace App\Repositories;
use Config;
//use App\Repositories\RepositoryInterface;


abstract class AbstractRepository  {

	protected $model;

	public function __construct()
	{
		$this->model = \App::make($this->modelClassName);
	}

	public function get($columns = array('*'))
	{
		return $this->model->get($columns);
	}

    public function paginateForApi($perpage = NULL)
    {

        $perpage                =   ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
        $data                   =   $this->model->orderBy('_id')->paginate($perpage)->toArray();

        $responeData                                    =   [];
        $responeData['list']                            =   (isset($data['data'])) ? $data['data'] : [];
        $responeData['paginate_data']['total']          =   (isset($data['total'])) ? $data['total'] : 0;
        $responeData['paginate_data']['per_page']       =   (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['per_page']       =   (isset($data['per_page'])) ? $data['per_page'] : 0;
        $responeData['paginate_data']['current_page']   =   (isset($data['current_page'])) ? $data['current_page'] : 0;
        $responeData['paginate_data']['last_page']      =   (isset($data['last_page'])) ? $data['last_page'] : 0;
        $responeData['paginate_data']['from']           =   (isset($data['from'])) ? $data['from'] : 0;
        $responeData['paginate_data']['to']             =   (isset($data['to'])) ? $data['to'] : 0;

        return $responeData;
    }

	public function paginate($perpage = NULL)
	{
		$perpage = ($perpage == NULL) ? Config::get('app.perpage') : intval($perpage);
		return $this->model->orderBy('_id')->paginate($perpage);
	}

	public function find($id)
	{
//        echo $id;exit;
        $row = $this->model->find($id);
//        var_dump($row);exit;
        return $row;
	}

	public function findBySlug($slug)
	{
		return $this->model->where('slug', str_slug(trim($slug)))->first();
	}


	public function store($data)
	{
		$recodset = new $this->model($data);
		$recodset->save();
        return $recodset;
	}

	public function update($data, $id)
	{
        $recodset = $this->model->findOrFail($id);
		$recodset->update($data);
        return $recodset;
	}

	
	public function destroy($id)
	{
        $recodset = $this->model->findOrFail($id);
		return $recodset->update(['status' => 'deleted']);
	}

	public function delete($id)
	{
        $recodset = $this->model->findOrFail($id);
		return $this->model->destroy(intval($id));
	}

	public function activelists()
	{
		return $this->model->active()->orderBy('name')->lists('name','_id');
	}

	public function activelistswithslug($orderby = '')
	{
		return $this->model->active()->orderBy('name')->get(['_id', 'name', 'slug']);
	}


    public function checkUniqueOnUpdate($id, $column, $value)
    {
        return $this->model->where(trim($column), trim($value))->whereNotIn('_id', [$id])->count();

    }

}

?>