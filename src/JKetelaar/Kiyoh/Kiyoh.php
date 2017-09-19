<?php

namespace JKetelaar\Kiyoh;

use GuzzleHttp\Client;
use JKetelaar\Kiyoh\Models\AverageScores;
use JKetelaar\Kiyoh\Models\Category;
use JKetelaar\Kiyoh\Models\Customer;
use JKetelaar\Kiyoh\Models\Question;
use JKetelaar\Kiyoh\Models\Review;
use JKetelaar\Kiyoh\Models\Company;

/**
 * @author JKetelaar
 */
class Kiyoh {

	const RECENT_COMPANY_REVIEWS_URL = 'https://www.kiyoh.nl/xml/recent_company_reviews.xml?connectorcode=%s&company_id=%s&page=%s';

	/**
	 * @var string
	 */
	private $connectorCode;

	/**
	 * @var int
	 */
	private $companyCode;

	/**
	 * @var Client
	 */
	private $client;

	/**
	 * Kiyoh constructor.
	 *
	 * @param string $connectorCode
	 * @param int    $companyCode
	 */
	public function __construct( $connectorCode, $companyCode ) {
		$this->connectorCode = $connectorCode;
		$this->companyCode   = $companyCode;
		$this->client        = new Client();
	}

    /**
     * Gets the 10 latest reviews
     *
     * @param int $page
     *
     * @return \JKetelaar\Kiyoh\Models\Review[]
     */
	public function getReviews($page = 1){
		return $this->parseReviews($this->getContent($page));
	}

    /**
     * Gets company info, category, (average) scores, etc.
     *
     * @return Company
     */
	public function getCompany() {
	    return $this->parseCompany($this->getContent());
    }

    /**
     * @param int $page
     *
     * @return string
     */
	public function getContent($page = 1) {
		return
            $this->getClient()->request(
                'GET',
                $this->getRecentCompanyReviewsURL($page)
            )
            ->getBody()
            ->getContents()
        ;
	}

	/**
	 * @return Client
	 */
	public function getClient() {
		return $this->client;
	}

    /**
     * Returns parsed Recent Company Reviews URL
     *
     * @param int $page
     *
     * @return string
     */
	public function getRecentCompanyReviewsURL($page = 1) {
		return sprintf(
		    self::RECENT_COMPANY_REVIEWS_URL,
            $this->connectorCode,
            $this->companyCode,
            $page
        );
	}

    /**
     * @param string|null $content
     *
     * @return Review[]
     */
    protected function parseReviews( $content = null ) {
        if ( $content === null ) {
            $content = $this->getContent();
        }

        $reviewsArray = [];
        $content      = simplexml_load_string( $content );
        $reviews      = $content->review_list->review;

        if ( $reviews->count() > 0 ) {
            foreach ( $reviews as $r ) {
                $rCustomer = $r->customer;
                $customer  = new Customer(
                    $this->elementToString( $rCustomer->name ),
                    $this->elementToString( $rCustomer->place )
                );

                $questions  = [];
                $rQuestions = $r->questions->question;
                foreach ( $rQuestions as $q ) {
                    $id          = $this->elementToString( $q->id );
                    $title       = $this->elementToString( $q->title );
                    $score       = $this->elementToString( $q->score );
                    $questions[] = new Question( $id, $title, $score );
                }

                $id = $this->elementToString( $r->id );
                $date = new \DateTime($this->elementToString( $rCustomer->date ));
                $totalScore = $this->elementToString( $r->totalScore );
                $recommended = $this->elementToString( $r->recommended );
                $pros = $this->elementToString( $r->positive );
                $cons = $this->elementToString( $r->negative );

                $reviewsArray[] = new Review(
                    $id,
                    $customer,
                    $date,
                    $totalScore,
                    $questions,
                    $recommended,
                    $pros,
                    $cons
                );
            }
        }

        return $reviewsArray;
    }

    /**
     * @param string|null $content
     *
     * @return Company
     */
    protected function parseCompany( $content = null ) {
        if ( $content === null ) {
            $content = $this->getContent();
        }

        $content = simplexml_load_string( $content );
        $cCompany = $content->company;

        $questions  = [];
        $cQuestions = $cCompany->average_scores->questions->question;
        foreach ( $cQuestions as $q ) {
            $id          = $this->elementToString( $q->id );
            $title       = $this->elementToString( $q->title );
            $score       = $this->elementToString( $q->score );
            $questions[] = new Question( $id, $title, $score );
        }

        $company = new Company(
            (int) $this->companyCode,
            $this->elementToString($cCompany->name),
            $this->elementToString($cCompany->url),
            new Category(
                (int) $this->elementToString($cCompany->category->id),
                $this->elementToString($cCompany->category->title)
            ),
            (float) $this->elementToString($cCompany->total_score),
            new AverageScores(
                $questions,
                (int) $this->elementToString($cCompany->average_scores->review_amount)
            ),
            (int) $this->elementToString($cCompany->total_reviews),
            (int) $this->elementToString($cCompany->total_views)
        );

        return $company;
    }

	/**
	 * @param \SimpleXMLElement $object
	 *
	 * @return string|null
	 */
	private function elementToString( $object ) {
		$result = $object->__toString();

		return strlen( $result ) > 0 ? $result : null;
	}
}