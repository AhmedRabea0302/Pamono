<?php 

	namespace App\Controller;


	use App\Entity\Image;
	use App\Repository\ImageRepository;
	use Doctrine\ORM\EntityManagerInterface;
	use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
	use Symfony\Component\HttpFoundation\JsonResponse;
	use Symfony\Component\HttpFoundation\Request;
	use Symfony\Component\Routing\Annotation\Route;
	use Psr\Log\LoggerInterface;

	/**
	 * Class PostController
	 * @package App\Controller
	 * @Route("/api", name="image")
	 */
	class ImageController extends AbstractController
	{	

		private $logger;

		public function __construct(LoggerInterface $logger)
		{
			$this->logger = $logger;
		}

		/**
		 * @param Request $request
		 * @param EntityManagerInterface $entityManager
		 * @param ImageRepository $imageRepository
		 * @return JsonResponse
		 * @throws \Exception
		 * @Route("/add-image", name="add_image", methods={"POST"})
		 */
		public function addImage(Request $request, EntityManagerInterface $entityManager){
			// Get cuurent Time and request base64code 
			$currentTime = time();
			$imageBase64 = $request->get('imageb64');

			try{
				
				$request = $this->transformJsonBody($request);

				if (!$request || !$imageBase64 || !$request->request->get('imageb64')){
					throw new \Exception();
				}

				// Validate The Image
                $valisateResult = $this->validateImage($imageBase64, $currentTime);
				
				if($valisateResult) {

					// Add Image To Database
					$image = new Image();
					$image->setName($currentTime . '.jpg');
					$entityManager->persist($image);
					$entityManager->flush();

					$data = [
						'status' => 200,
						'image_id' => $image->getId(),
						'image_path' => '/api/get-image/' . $image->getId(),
						'success' => "Image added successfully",
					];


					$this->addToLogger($data, 'info', 'Image Added Successfully: ');

					return $this->response($data);
				} else {
					$min_image_size = $this->getParameter('min_image_size');

					$this->addToLogger('', 'error', 'Image Must be in jpg format and with minimum size of $min_image_size Megabytes');

					return $this->response([
						"message" => "Image Must be in jpg format and with minimum size of $min_image_size Megabytes"
					]);
				}
				

			}catch (\Exception $e){
				$data = [
					'status' => 422,
					'errors' => "Data not valid",
				];
				return $this->response($data, 422);
			}

		}

		private function validateImage($base64, $currentTime) {
			
			if(preg_match('[image/jpg|image/jpeg]', $base64)) {
			
				// Create Temp image Path And Upload it to temp Directory
				$TempPath = $this->getParameter('temp_directory') . $currentTime.".jpg";
				file_put_contents($TempPath, base64_decode(
					str_replace(['data:image/jpg;base64,','data:image/jpeg;base64,'], '', $base64)
				));

				// Get Image Size In Megabytes
				$ImageSize =  filesize($TempPath) / 1024 / 1024;
				if(!( $ImageSize < $this->getParameter('min_image_size') )){

					// add image file in filesystem
					file_put_contents($this->getParameter('image_directory') . $currentTime . '.jpg', base64_decode(
						str_replace(['data:image/jpg;base64,','data:image/jpeg;base64,'], '', $base64)
					));			

					// Remove Temporary Image From filesystem
					unlink($TempPath);
					return true;
				}else{
					// Remove Temporary Image From filesystem
					unlink($TempPath);
					return false;
				}
			} else {
				return false;
			}
		}

		/**
		 * @param Request $request
		 * @param EntityManagerInterface $entityManager
		 * @param ImageRepository $imageRepository
		 * @return JsonResponse
		 * @throws \Exception
		 * @Route("/get-image/{imageId}", name="get_image", methods={"get"})
		 */
		public function getImageUrl(ImageRepository $imageRepository, $imageId) {
			$data = $imageRepository->find($imageId);
			if($data) {
				return $this->response([
					'image_path' => '/storage/images/' . $data->getName()
				]);
			} else {
				return $this->response(['Message' => 'No Image With This Id']);
			}
		}

		// Add To Dev Loger
		private function addToLogger($data, $type, $message) {
			$this->logger->$type($message, [
				$data, $this->getUser()->getEmail(), $this->getUser()->getUserName()
			]);
		}

        public function response($data, $status = 200, $headers = [])
		{
			return new JsonResponse($data, $status, $headers);
		}

        protected function transformJsonBody(\Symfony\Component\HttpFoundation\Request $request)
		{
			$data = json_decode($request->getContent(), true);

			if ($data === null) {
				return $request;
			}

			$request->request->replace($data);

			return $request;
		}


    }