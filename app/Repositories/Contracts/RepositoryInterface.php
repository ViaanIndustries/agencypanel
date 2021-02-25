<?PHP

namespace App\Repositories\Contracts;

/**
 * RepositoryInterface provides the standard functions to be expected of ANY
 * repository.
 */
interface RepositoryInterface {

    public function get();

    public function find($id);

//     public function create(array $attributes = []);

//     public function update($id, array $attributes = []);

//     public function delete($id);

}