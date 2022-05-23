<?php

namespace App\Service;

use App\Entity\Actor;
use App\Entity\Director;
use App\Entity\Movie;
use App\Entity\TvShow;
use App\Entity\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class AddToDbService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function addMovies(array $movies): array
    {
        $moviesAlreadyInDb = [];
        $moviesAdded = [];

        foreach ($movies as $movieData) {
            $movieInDb = $this->findMovieInDb($movieData['id']);

            if ($movieInDb['status'] === 'OK') {

                $apiKey = "aec4d5d7be64f05cf44a29476e490ca5";
                $movieUrl = 'https://api.themoviedb.org/3/movie/' . $movieData['id'] . '?api_key=' . $apiKey . '&language=fr-FR';
                $movieCreditsUrl = 'https://api.themoviedb.org/3/movie/' . $movieData['id'] . '/credits?api_key=' . $apiKey . '&language=fr-FR';
                $movieResult = json_decode((string) file_get_contents($movieUrl), true);
                $movieCredits = json_decode((string) file_get_contents($movieCreditsUrl), true);


                $movie = new Movie();
                $movie->setTitle($movieData['title']);
                $movie->setOriginalTitle($movieData['original_title']);
                $movie->setSynopsis($movieData['overview']);
                $movie->setPosterPath($movieData['poster_path']);
                $movie->setPopularity($movieData['popularity']);
                $movie->setYear(explode('-', $movieResult['release_date'])[0]);
                $movie->setMovieDbId($movieData['id']);

                $types = $this->getTypes($movieResult['genres']);
                foreach ($types as $type) {
                    $movie->addType($type);
                }

                $actors = $this->getActors($movieCredits['cast']);
                foreach ($actors as $actor) {
                    $movie->addMainActor($actor);
                }

                $directors = $this->getDirectors($movieCredits['crew']);
                foreach ($directors as $director) {
                    $movie->addDirector($director);
                }

                $this->entityManager->persist($movie);
                $this->entityManager->flush();

                $moviesAdded[] = $movieData;
            } else {
                $moviesAlreadyInDb[] = $movieData;
            }
        }

        return [
            'moviesAlreadyInDb' => $moviesAlreadyInDb,
            'moviesAdded' => $moviesAdded
        ];
    }

    public function addTvShows(array $tvShows): array
    {
        $tvShowsAlreadyInDb = [];
        $tvShowsAdded = [];

        foreach ($tvShows as $tvShowData) {

            $tvShowInDb = $this->findTvShowInDb($tvShowData['id']);

            if ($tvShowInDb['status'] === 'OK') {

                $apiKey = "aec4d5d7be64f05cf44a29476e490ca5";
                $tvShowUrl = 'https://api.themoviedb.org/3/tv/' . $tvShowData['id'] . '?api_key=' . $apiKey . '&language=fr-FR';
                $tvShowCreditsUrl = 'https://api.themoviedb.org/3/tv/' . $tvShowData['id'] . '/credits?api_key=' . $apiKey . '&language=fr-FR';
                $tvShowResults = json_decode((string) file_get_contents($tvShowUrl), true);
                $tvShowCredits = json_decode((string) file_get_contents($tvShowCreditsUrl), true);

                $tvShow = new TvShow();
                $tvShow->setTitle($tvShowData['name']);
                $tvShow->setOriginalTitle($tvShowData['original_name']);
                $tvShow->setPosterPath($tvShowData['poster_path']);
                $tvShow->setMovieDbId($tvShowData['id']);
                $tvShow->setPopularity($tvShowData['popularity']);
                $tvShow->setYear(explode('-', $tvShowResults['first_air_date'])[0]);

                $types = $this->getTypes($tvShowResults['genres']);
                foreach ($types as $type) {
                    $tvShow->addType($type);
                }

                $actors = $this->getActors($tvShowCredits['cast']);
                foreach ($actors as $actor) {
                    $tvShow->addActor($actor);
                }

                $this->entityManager->persist($tvShow);
                $this->entityManager->flush();

                $tvShowsAdded[] = $tvShowData;
            } else {
                $tvShowsAlreadyInDb[] = $tvShowData;
            }
        }

        return [
            'tvShowsAlreadyInDb' => $tvShowsAlreadyInDb,
            'tvShowsAdded' => $tvShowsAdded
        ];
    }

    private function findMovieInDb(int $id): array
    {
        $movie = $this->entityManager->getRepository(Movie::class)->findOneBy(['movieDbId' => $id]);

        if (is_null($movie)) {
            return ['status' => 'OK'];
        } else {
            return ['status' => 'KO'];
        }
    }

    private function findTvShowInDb(int $id): array
    {
        $tvShow = $this->entityManager->getRepository(TvShow::class)->findOneBy(['movieDbId' => $id]);

        if (is_null($tvShow)) {
            return ['status' => 'OK'];
        } else {
            return ['status' => 'KO'];
        }
    }

    private function getTypes(array $genres): array
    {
        $types = [];
        foreach ($genres as $genre) {
            $type = $this->entityManager->getRepository(Type::class)->findOneBy(['movieDbId' => $genre['id']]);

            if (is_null($type)) {
                $type = new Type();
                $type->setMovieDbId($genre['id']);
                $type->setName($genre['name']);

                $this->entityManager->persist($type);
            }

            $types[] = $type;
        }

        $this->entityManager->flush();

        return $types;
    }

    private function getActors(array $cast): array
    {
        $actors = [];
        foreach ($cast as $people) {
            $actor = $this->entityManager->getRepository(Actor::class)->findOneBy(['movieDbId' => $people['id']]);

            if (is_null($actor)) {
                $actor = new Actor();
                $actor->setName($people['name']);
                $actor->setMovieDbId($people['id']);

                $this->entityManager->persist($actor);
            }

            $actors[] = $actor;
        }

        $this->entityManager->flush();

        return $actors;
    }

    public function getDirectors(array $crew): array
    {
        $directors = [];
        foreach ($crew as $people) {
            if ($people['job'] === 'Director') {
                $director = $this->entityManager->getRepository(Director::class)->findOneBy(['movieDbId' => $people['id']]);

                if (is_null($director)) {
                    $director = new Director();
                    $director->setName($people['name']);
                    $director->setMovieDbId($people['id']);

                    $this->entityManager->persist($director);
                }

                $directors[] = $director;
            }
        }

        $this->entityManager->flush();

        return $directors;
    }
}